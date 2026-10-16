<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImages\ReorderProductImages;
use App\Services\ProductImages\SelectPrimaryProductImage;
use App\Support\ProductImageIdentifier;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Two-session proof that primary selection and reordering stay consistent on a
 * real MySQL/MariaDB server. SQLite cannot demonstrate this: the competing
 * writer is blocked by the product-row lock and the last-resort guarantee is the
 * `is_primary_guard` unique index, neither of which the in-memory suite can
 * exercise across sessions.
 *
 * The suite deliberately avoids `RefreshDatabase` — its wrapping transaction
 * would hide one session's uncommitted work from the other — and rebuilds the
 * disposable schema itself.
 */
final class ProductImageMutationConcurrencyMysqlTest extends TestCase
{
    private const LOCK_WAIT_TIMEOUT_ERROR = 1205;

    private const DUPLICATE_ENTRY_ERROR = 1062;

    private const SECOND_CONNECTION = 'product_image_race_writer';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private string $defaultConnection;

    private Connection $secondWriter;

    private bool $setUpCompleted = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires a real concurrent MySQL/MariaDB server.');
        }

        $this->assertDisposableDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->setUpCompleted = true;

        $this->defaultConnection = DB::getDefaultConnection();
        $this->secondWriter = $this->independentConnection();
    }

    protected function tearDown(): void
    {
        if (isset($this->defaultConnection)) {
            DB::setDefaultConnection($this->defaultConnection);
            DB::purge(self::SECOND_CONNECTION);
        }

        // `migrate:fresh` keeps the schema consistent with the migrations table;
        // dropping tables instead would break a later RefreshDatabase suite,
        // because `RefreshDatabaseState::$migrated` is process-wide. PHPUnit still
        // calls tearDown() after a setUp() failure, so the rebuild is gated on this
        // suite having reached it against the disposable database — otherwise it
        // would wipe whichever database happens to be connected.
        if ($this->setUpCompleted && $this->isDisposableDatabase()) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        parent::tearDown();
    }

    private function assertDisposableDatabase(): void
    {
        if (app()->environment('production')) {
            $this->fail('Refusing to run destructive concurrency migrations in production.');
        }

        $this->assertSame(
            self::DISPOSABLE_DATABASE,
            DB::connection()->getDatabaseName(),
            'This suite must only run against the disposable test database.',
        );
    }

    private function isDisposableDatabase(): bool
    {
        return app()->environment() !== 'production'
            && DB::getDriverName() === 'mysql'
            && DB::connection()->getDatabaseName() === self::DISPOSABLE_DATABASE
            && Schema::hasTable('migrations');
    }

    public function test_a_held_product_lock_serializes_a_competing_primary_selection(): void
    {
        [$product, $primary, $target] = $this->fixture();
        $first = DB::connection();
        $this->assertDistinctSessions($first, $this->secondWriter);

        $first->beginTransaction();
        $first->table('products')->where('id', $product->getKey())->lockForUpdate()->get();
        $this->secondWriter->statement('SET SESSION innodb_lock_wait_timeout = 3');

        $blocked = $this->selectOnSecondConnection($product, $target);

        $this->assertNotNull($blocked, 'A competing selection must not run while the product row is locked.');
        $this->assertSame(self::LOCK_WAIT_TIMEOUT_ERROR, (int) ($blocked->errorInfo[1] ?? 0));

        $first->rollBack();

        app(SelectPrimaryProductImage::class)->select($product, $target);

        $this->assertSame(
            [$target->getKey()],
            $this->primaries($product),
            'After the lock is released exactly the requested image is primary.',
        );
        $this->assertFalse($primary->fresh()->is_primary);
    }

    public function test_the_unique_guard_refuses_a_second_primary(): void
    {
        [$product, $primary, $target] = $this->fixture();

        $this->secondWriter->statement('SET SESSION innodb_lock_wait_timeout = 3');
        $second = null;

        try {
            $this->secondWriter->table('product_images')
                ->where('id', $target->getKey())
                ->update(['is_primary' => true]);
        } catch (QueryException $exception) {
            $second = $exception;
        }

        $this->assertNotNull($second, 'The database must refuse a second primary even when the lock is bypassed.');
        $this->assertSame(self::DUPLICATE_ENTRY_ERROR, (int) ($second->errorInfo[1] ?? 0));
        $this->assertSame([$primary->getKey()], $this->primaries($product));
    }

    public function test_a_held_product_lock_serializes_a_competing_reorder(): void
    {
        [$product, $primary, $second, $third] = $this->fixture();
        $first = DB::connection();
        $this->assertDistinctSessions($first, $this->secondWriter);

        $order = [
            ProductImageIdentifier::encode($third),
            ProductImageIdentifier::encode($primary),
            ProductImageIdentifier::encode($second),
        ];

        $first->beginTransaction();
        $first->table('products')->where('id', $product->getKey())->lockForUpdate()->get();
        $this->secondWriter->statement('SET SESSION innodb_lock_wait_timeout = 3');

        $blocked = $this->reorderOnSecondConnection($product, $order);

        $this->assertNotNull($blocked, 'A competing reorder must not run while the product row is locked.');
        $this->assertSame(self::LOCK_WAIT_TIMEOUT_ERROR, (int) ($blocked->errorInfo[1] ?? 0));
        $this->assertSame([1, 2, 3], $this->sortedPositions($product), 'The blocked attempt must leave no partial ordering.');

        $first->rollBack();

        app(ReorderProductImages::class)->reorder($product, $order);
        $this->assertSame(
            [$third->getKey(), $primary->getKey(), $second->getKey()],
            $this->orderedIds($product),
        );

        app(ReorderProductImages::class)->reorder($product, array_reverse($order));
        $this->assertSame(
            [$second->getKey(), $primary->getKey(), $third->getKey()],
            $this->orderedIds($product),
            'The last accepted reorder wins deterministically, with no partial mixing of the two orders.',
        );
        $this->assertSame([1, 2, 3], $this->sortedPositions($product));
    }

    /** @return array<int, ProductImage> */
    private function fixture(): array
    {
        $product = Product::factory()->create();
        $primary = ProductImage::factory()->for($product)->create(['sort_order' => 1, 'is_primary' => true]);
        $second = ProductImage::factory()->for($product)->create(['sort_order' => 2]);
        $third = ProductImage::factory()->for($product)->create(['sort_order' => 3]);

        return [$product, $primary, $second, $third];
    }

    private function selectOnSecondConnection(Product $product, ProductImage $target): ?QueryException
    {
        return $this->onSecondConnection(
            fn () => app(SelectPrimaryProductImage::class)->select($product, $target),
        );
    }

    /** @param list<string> $order */
    private function reorderOnSecondConnection(Product $product, array $order): ?QueryException
    {
        return $this->onSecondConnection(
            fn () => app(ReorderProductImages::class)->reorder($product, $order),
        );
    }

    private function onSecondConnection(callable $operation): ?QueryException
    {
        DB::setDefaultConnection(self::SECOND_CONNECTION);

        try {
            $operation();
        } catch (QueryException $exception) {
            return $exception;
        } finally {
            DB::setDefaultConnection($this->defaultConnection);
        }

        return null;
    }

    /** @return list<int> */
    private function primaries(Product $product): array
    {
        return ProductImage::query()
            ->where('product_id', $product->getKey())
            ->where('is_primary', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /** @return list<int> */
    private function orderedIds(Product $product): array
    {
        return ProductImage::query()
            ->where('product_id', $product->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /** @return list<int> */
    private function sortedPositions(Product $product): array
    {
        $positions = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->pluck('sort_order')
            ->all();

        sort($positions);

        return $positions;
    }

    private function independentConnection(): Connection
    {
        $config = config('database.connections.mysql');

        config()->set('database.connections.'.self::SECOND_CONNECTION, $config);
        DB::purge(self::SECOND_CONNECTION);

        return DB::connection(self::SECOND_CONNECTION);
    }

    private function assertDistinctSessions(Connection $first, Connection $second): void
    {
        $this->assertNotSame(
            $first->selectOne('SELECT CONNECTION_ID() AS id')->id,
            $second->selectOne('SELECT CONNECTION_ID() AS id')->id,
            'The second writer must be a separate database session for this to be a concurrency test.',
        );
    }
}
