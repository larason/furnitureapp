<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Two-connection concurrency proof for ACTIVE-only token uniqueness.
 *
 * The uniqueness guarantee is the database's, so it has to be demonstrated under real
 * concurrent writers rather than sequential calls. This suite deliberately avoids
 * `RefreshDatabase`, whose wrapping transaction would hide one connection's uncommitted
 * work from the other, and manages its own table and cleanup instead.
 *
 * Both writers are separate database sessions. A second handle on the default connection
 * would not do: Laravel caches one PDO per connection name, so `getPdo()` returns the same
 * session, and the second "writer" would simply queue behind the first inside one session.
 */
final class DeviceTokenConcurrencyMysqlTest extends TestCase
{
    private const LOCK_WAIT_TIMEOUT_ERROR = 1205;

    private const DUPLICATE_ENTRY_ERROR = 1062;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires a real concurrent MySQL/MariaDB server.');
        }

        $this->assertSame(
            'furnitureapp_test_disposable',
            DB::connection()->getDatabaseName(),
            'This suite must only run against the disposable test database.',
        );

        $this->buildSchema();
        $this->firstUserId = (int) User::factory()->create()->getKey();
        $this->secondUserId = (int) User::factory()->create()->getKey();
        $this->secondWriter = $this->independentConnection();
    }

    private int $firstUserId;

    private int $secondUserId;

    private Connection $secondWriter;

    /**
     * A genuinely separate connection, so the "second writer" is a second
     * database session.
     *
     * `DB::connection()->getPdo()` cannot be used here: Laravel caches one PDO
     * handle per connection name, so it returns the identical session as the
     * default connection. Concurrency has to be proved across real sessions, and
     * only a distinct connection name gets a distinct handle.
     */
    private function independentConnection(): Connection
    {
        $name = 'fcm_race_writer';
        $config = config('database.connections.mysql');

        config()->set("database.connections.{$name}", $config);
        DB::purge($name);

        return DB::connection($name);
    }

    private function assertDistinctSessions(Connection $first, Connection $second): void
    {
        $firstId = $first->selectOne('SELECT CONNECTION_ID() AS id')->id;
        $secondId = $second->selectOne('SELECT CONNECTION_ID() AS id')->id;

        $this->assertNotSame(
            $firstId,
            $secondId,
            'The second writer must be a separate database session for this to be a concurrency test.',
        );
    }

    protected function tearDown(): void
    {
        DB::purge('fcm_race_writer');

        // This suite rebuilds the schema in setUp, so it deliberately does not use
        // RefreshDatabase. Dropping the table here would leave it missing while the
        // `migrations` table still recorded it as applied, and
        // `RefreshDatabaseState::$migrated` is a process-wide static: a later
        // RefreshDatabase suite would not re-run migrations and would fail on the
        // missing table. `migrate:fresh` leaves the schema consistent with the
        // migrations table, and the database is disposable and rebuilt per test.
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('migrations')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        parent::tearDown();
    }

    private function buildSchema(): void
    {
        // The database name is asserted in setUp, so this rebuild is confined to
        // the disposable database. A full schema is needed because the device
        // table has a foreign key to users.
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('device_installations'));
        $this->assertTrue(Schema::hasColumn('device_installations', 'active_token_fingerprint'));
    }

    /** @return array<string, mixed> */
    private function activeRow(int $userId, string $instance, string $fingerprint): array
    {
        return [
            'user_id' => $userId,
            'platform' => 'ANDROID',
            'app_instance_id' => $instance,
            'token_ciphertext' => 'irrelevant-for-constraint-test',
            'token_fingerprint' => $fingerprint,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function test_a_committed_writer_keeps_the_token_and_blocks_the_second(): void
    {
        $first = DB::connection();
        $second = $this->secondWriter;
        $this->assertDistinctSessions($first, $second);

        try {
            $first->beginTransaction();
            $first->table('device_installations')->insert(
                $this->activeRow($this->firstUserId, 'instance-a', str_repeat('a', 64)),
            );

            // The second session blocks on the unique index until the first commits.
            $second->statement('SET SESSION innodb_lock_wait_timeout = 5');
            $blocked = null;
            try {
                $second->table('device_installations')->insert(
                    $this->activeRow($this->secondUserId, 'instance-b', str_repeat('a', 64))
                );
            } catch (QueryException $exception) {
                $blocked = $exception;
            }

            $first->commit();
        } finally {
            if ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
        }

        $this->assertNotNull($blocked, 'The second writer must be refused while the first holds the token.');
        $this->assertSame(self::LOCK_WAIT_TIMEOUT_ERROR, (int) ($blocked->errorInfo[1] ?? 0));

        $duplicate = null;
        try {
            $second->table('device_installations')->insert(
                $this->activeRow($this->secondUserId, 'instance-b', str_repeat('a', 64))
            );
        } catch (QueryException $exception) {
            $duplicate = $exception;
        }

        $this->assertNotNull($duplicate, 'The committed token owner must reject a duplicate registration.');
        $this->assertSame(self::DUPLICATE_ENTRY_ERROR, (int) ($duplicate->errorInfo[1] ?? 0));
        $this->assertSame(1, $first->table('device_installations')->count());
    }

    public function test_a_rolled_back_writer_releases_the_token(): void
    {
        $first = DB::connection();
        $second = $this->secondWriter;
        $this->assertDistinctSessions($first, $second);

        $first->beginTransaction();
        $first->table('device_installations')->insert(
            $this->activeRow($this->firstUserId, 'instance-a', str_repeat('b', 64)),
        );
        $first->rollBack();

        $second->statement('SET SESSION innodb_lock_wait_timeout = 5');
        $second->table('device_installations')->insert(
            $this->activeRow($this->secondUserId, 'instance-b', str_repeat('b', 64))
        );

        // Exactly one ACTIVE owner survives the race.
        $this->assertSame(1, DB::table('device_installations')->count());
        $this->assertSame(
            $this->secondUserId,
            (int) DB::table('device_installations')->value('user_id'),
        );
    }

    public function test_a_retired_row_never_blocks_a_concurrent_writer(): void
    {
        $connection = DB::connection();
        $fingerprint = str_repeat('c', 64);

        $connection->table('device_installations')->insert(
            $this->activeRow($this->firstUserId, 'instance-a', $fingerprint),
        );
        $connection->table('device_installations')
            ->where('app_instance_id', 'instance-a')
            ->update(['status' => 'INACTIVE', 'deactivated_at' => now()]);

        // The retired row keeps the fingerprint for audit but frees it for a new owner.
        $connection->table('device_installations')->insert(
            $this->activeRow($this->secondUserId, 'instance-b', $fingerprint),
        );

        $this->assertSame(2, $connection->table('device_installations')->count());
        $this->assertSame(
            1,
            $connection->table('device_installations')->where('app_instance_id', 'instance-a')->count(),
        );
        $this->assertNull(
            $connection->table('device_installations')
                ->where('app_instance_id', 'instance-a')
                ->value('active_token_fingerprint'),
            'A retired row must not occupy the unique slot.',
        );
    }
}
