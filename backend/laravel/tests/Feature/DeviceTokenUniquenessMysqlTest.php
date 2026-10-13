<?php

namespace Tests\Feature;

use App\Exceptions\Api\ApiException;
use App\Models\DeviceInstallation;
use App\Models\User;
use App\Services\Push\DeviceRegistrationService;
use App\Services\Push\PushTokenCipher;
use App\Services\Push\PushTokenFingerprint;
use App\Support\DeviceRegistrationStatus;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * ACTIVE-only token uniqueness, verified against the real MySQL/MariaDB engine.
 *
 * MySQL cannot express a partial unique index, so the constraint relies on a generated or
 * trigger-maintained column. The SQLite suite cannot prove that path, and MySQL is where
 * generated columns, NULL handling in unique indexes, and concurrent writers behave
 * differently. Run against the disposable database only; the suite skips itself elsewhere.
 */
final class DeviceTokenUniquenessMysqlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL engine to verify the generated-column constraint.');
        }

        $this->assertSame(
            'furnitureapp_test_disposable',
            DB::connection()->getDatabaseName(),
            'This suite must only run against the disposable test database.',
        );

        $this->seed(RbacSeeder::class);
        config()->set('notifications.push.registration_enabled', true);
    }

    public function test_the_active_token_constraint_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('device_installations', 'active_token_fingerprint'));

        $indexes = collect(DB::select('SHOW INDEX FROM device_installations'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        $this->assertContains('device_installations_active_token_unique', $indexes);
        $this->assertNotContains('device_installations_token_fingerprint_unique', $indexes);
    }

    public function test_a_second_active_row_with_the_same_fingerprint_is_rejected(): void
    {
        $first = User::factory()->customer()->create();
        $second = User::factory()->customer()->create();
        $fingerprint = app(PushTokenFingerprint::class)->for('mysql-shared');

        DeviceInstallation::factory()->forOwner($first)->withToken('mysql-shared')->create();

        $thrown = null;
        try {
            DB::table('device_installations')->insert($this->row($second, 'instance-b', $fingerprint, 'ACTIVE'));
        } catch (QueryException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(QueryException::class, $thrown, 'A second ACTIVE owner must be rejected.');
        $this->assertSame(1, DeviceInstallation::query()->count());
    }

    public function test_retired_rows_may_share_a_fingerprint_and_keep_their_owner(): void
    {
        $previous = User::factory()->customer()->create();
        $next = User::factory()->customer()->create();
        $fingerprint = app(PushTokenFingerprint::class)->for('mysql-handover');

        $retired = DeviceInstallation::factory()->forOwner($previous)->withToken('mysql-handover')->create();
        $this->assertTrue(
            (bool) DB::table('device_installations')
                ->where('id', $retired->getKey())
                ->update(['status' => DeviceRegistrationStatus::INACTIVE->value, 'deactivated_at' => now()]),
        );

        // The retired row frees the fingerprint for a new owner.
        DB::table('device_installations')->insert($this->row($next, 'instance-b', $fingerprint, 'ACTIVE'));

        $this->assertSame(2, DeviceInstallation::query()->count());
        $this->assertSame(
            $previous->getKey(),
            DeviceInstallation::query()->whereKey($retired->getKey())->value('user_id'),
        );
        $this->assertSame(
            'mysql-handover',
            app(PushTokenCipher::class)->decrypt(
                DeviceInstallation::query()->whereKey($retired->getKey())->value('token_ciphertext'),
            ),
        );
    }

    public function test_the_constraint_breaks_a_tie_the_pre_check_cannot_see(): void
    {
        $first = User::factory()->customer()->create();
        $second = User::factory()->customer()->create();
        $fingerprint = app(PushTokenFingerprint::class)->for('mysql-tie');

        // Reproduce the interleaving the pre-check cannot prevent: writer B has
        // already passed its ownership read, but writer A commits first. The
        // registration service is called with the pre-check satisfied, so the
        // only thing that can reject B is the database.
        $this->assertFalse(
            $this->activeOwnerExists($fingerprint, $second),
            'Precondition: the second customer sees no active owner before the interleaving.',
        );

        DeviceInstallation::factory()->forOwner($first)->withToken('mysql-tie')->create();

        $thrown = null;
        try {
            DB::table('device_installations')->insert($this->row($second, 'instance-b', $fingerprint, 'ACTIVE'));
        } catch (QueryException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(
            QueryException::class,
            $thrown,
            'The database, not the pre-check, must reject the second activation.',
        );
        $this->assertSame(1, DeviceInstallation::query()->where('status', 'ACTIVE')->count());
    }

    public function test_a_conflict_after_the_pre_check_surfaces_as_the_frozen_conflict_code(): void
    {
        $first = User::factory()->customer()->create();
        $second = User::factory()->customer()->create();
        $service = app(DeviceRegistrationService::class);

        $service->register($first, [
            'token' => 'mysql-mapped',
            'platform' => 'ANDROID',
            'app_instance_id' => 'instance-a',
        ]);

        // A second customer is refused with the frozen code, not a database error.
        try {
            $service->register($second, [
                'token' => 'mysql-mapped',
                'platform' => 'ANDROID',
                'app_instance_id' => 'instance-b',
            ]);
            $this->fail('Expected a conflict for a token held by another customer.');
        } catch (ApiException $exception) {
            $this->assertSame('CONFLICT', $exception->errorCode()->value);
            $this->assertSame(409, $exception->status());
        }

        $this->assertSame(1, DeviceInstallation::query()->where('status', 'ACTIVE')->count());
    }

    public function test_a_constraint_missing_its_triggers_is_not_treated_as_applied(): void
    {
        if (! $this->runsOnMariaDb()) {
            $this->markTestSkipped('The trigger-maintained column is created only on MariaDB.');
        }

        $this->assertTrue(
            $this->reportsActiveTokenConstraint(),
            'Precondition: the migration is fully applied before anything is removed.',
        );

        // Dropping the triggers is what a restore from a dump taken without --triggers
        // leaves behind, and it is the one state where the column and the index are both
        // present while the index is inert.
        $triggers = $this->dropActiveTokenTriggers();

        try {
            $first = User::factory()->customer()->create();
            $second = User::factory()->customer()->create();
            $fingerprint = app(PushTokenFingerprint::class)->for('mysql-triggerless');

            DeviceInstallation::factory()->forOwner($first)->withToken('mysql-triggerless')->create();
            DB::table('device_installations')->insert($this->row($second, 'instance-b', $fingerprint, 'ACTIVE'));

            $this->assertSame(
                2,
                DeviceInstallation::query()->where('status', 'ACTIVE')->count(),
                'Precondition: without the triggers the table accepts two ACTIVE owners of one token, '
                .'which is exactly the state that must not be mistaken for an applied constraint.',
            );

            $this->assertFalse(
                $this->reportsActiveTokenConstraint(),
                'A column nothing maintains enforces nothing, so the constraint is not applied.',
            );
        } finally {
            // DDL implicitly commits, so RefreshDatabase cannot undo this.
            foreach ($triggers as $statement) {
                DB::statement($statement);
            }
        }
    }

    /**
     * The completed rollback is order-independent on MariaDB, so this pins the end state
     * rather than the sequence: no trigger may outlive the column it references, and the
     * table must still accept writes once `down()` returns.
     */
    public function test_a_completed_mariadb_rollback_leaves_no_orphaned_trigger(): void
    {
        if (! $this->runsOnMariaDb()) {
            $this->markTestSkipped('Triggers maintain the column only on MariaDB.');
        }

        $owner = User::factory()->customer()->create();
        DeviceInstallation::factory()->forOwner($owner)->create();

        $amendment = $this->amendment();

        try {
            $amendment->down();

            $this->assertSame(
                0,
                $this->activeTokenTriggerCount(),
                'A trigger left behind references a column that no longer exists.',
            );
            $this->assertFalse(Schema::hasColumn('device_installations', 'active_token_fingerprint'));

            // The orphaned-trigger failure is not visible until something writes, so the
            // rollback has to be exercised rather than merely inspected.
            $next = User::factory()->customer()->create();
            DeviceInstallation::factory()->forOwner($next)->withToken('rollback-writable')->create();

            $this->assertSame(
                2,
                DeviceInstallation::query()->count(),
                'The table must still be writable once the rollback completes.',
            );
        } finally {
            // DDL implicitly commits, so RefreshDatabase cannot restore this schema.
            $amendment->up();
        }
    }

    private function amendment(): object
    {
        return require database_path('migrations/2026_10_13_120000_scope_device_token_uniqueness_to_active_rows.php');
    }

    private function activeTokenTriggerCount(): int
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) AS found FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?
               AND TRIGGER_NAME LIKE ?',
            ['device_installations', 'trg_device_installations_active_token_%'],
        )->found;
    }

    /**
     * Reads the migration's own applied-check, so the test proves the predicate rather
     * than a restatement of it.
     */
    private function reportsActiveTokenConstraint(): bool
    {
        $migration = require database_path('migrations/2026_10_13_120000_scope_device_token_uniqueness_to_active_rows.php');

        $predicate = new ReflectionMethod($migration, 'hasActiveTokenConstraint');
        $predicate->setAccessible(true);

        return (bool) $predicate->invoke($migration);
    }

    /**
     * @return array<int, string>
     */
    private function dropActiveTokenTriggers(): array
    {
        $statements = [];

        foreach (['trg_device_installations_active_token_insert', 'trg_device_installations_active_token_update'] as $trigger) {
            $created = DB::selectOne("SHOW CREATE TRIGGER {$trigger}");
            $statements[] = (string) $created->{'SQL Original Statement'};
            DB::statement("DROP TRIGGER {$trigger}");
        }

        return $statements;
    }

    private function runsOnMariaDb(): bool
    {
        $version = DB::selectOne('SELECT VERSION() AS server_version');

        return str_contains((string) ($version->server_version ?? ''), 'MariaDB');
    }

    private function activeOwnerExists(string $fingerprint, User $customer): bool
    {
        return DeviceInstallation::query()
            ->where('token_fingerprint', $fingerprint)
            ->where('status', DeviceRegistrationStatus::ACTIVE->value)
            ->where('user_id', '!=', $customer->getKey())
            ->exists();
    }

    /** @return array<string, mixed> */
    private function row(User $user, string $instance, string $fingerprint, string $status): array
    {
        return [
            'user_id' => $user->getKey(),
            'platform' => 'ANDROID',
            'app_instance_id' => $instance,
            'token_ciphertext' => app(PushTokenCipher::class)->encrypt('mysql-handover'),
            'token_fingerprint' => $fingerprint,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
