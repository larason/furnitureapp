<?php

namespace Tests\Integration;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemInventoryAllocation;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryAllocator;
use App\Services\InventoryAdjustmentService;
use App\Support\ApiErrorCode;
use App\Support\InventoryAdjustmentReason;
use App\Support\ProductType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ConcurrentWorkerTimeout;
use Tests\TestCase;

/**
 * True MySQL/MariaDB concurrency verification for Phase 5.10.
 *
 * Not part of the default suite. Run explicitly against the disposable
 * database, e.g.:
 *
 *   INVENTORY_MYSQL_CONCURRENCY=1 \
 *   INVENTORY_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/InventoryConcurrencyMysqlTest.php
 */
class InventoryConcurrencyMysqlTest extends TestCase
{
    private const CONNECTION = 'mysql_concurrency';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private const ITERATIONS = 10;

    private const RESULT_SUCCESS = 0;

    private const RESULT_INSUFFICIENT = 1;

    private const RESULT_FAILURE = 2;

    private const CHILD_TIMEOUT_SECONDS = 30;

    private string $previousDefaultConnection = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');

        if (getenv('INVENTORY_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Set INVENTORY_MYSQL_CONCURRENCY=1 to run MySQL concurrency verification.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension required.');
        }

        if (! function_exists('posix_kill')) {
            $this->markTestSkipped('posix extension required for bounded concurrency-test cleanup.');
        }

        if (app()->environment('production')) {
            $this->fail('Refusing to run destructive concurrency tests in production.');
        }

        $database = (string) getenv('INVENTORY_MYSQL_TEST_DATABASE');

        if ($database !== self::DISPOSABLE_DATABASE) {
            $this->fail('INVENTORY_MYSQL_TEST_DATABASE must be exactly '.self::DISPOSABLE_DATABASE.'.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(
            config('database.connections.mysql'),
            ['database' => $database],
        )]);
        config(['database.default' => self::CONNECTION]);

        DB::purge(self::CONNECTION);
        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        config(['database.default' => $this->previousDefaultConnection]);

        parent::tearDown();
    }

    public function test_concurrent_reservation_of_the_last_unit_never_oversells(): void
    {
        $variant = $this->variant();
        $stock = $this->stock($variant, 'main', 1);

        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $stock->refresh()->update(['reserved_quantity' => 0]);
            OrderItemInventoryAllocation::query()->delete();

            [, $firstItem] = $this->orderWithItem($variant, 1);
            [, $secondItem] = $this->orderWithItem($variant, 1);

            $codes = $this->runChildren(
                fn (): int => $this->attemptReserve($firstItem->order_id),
                fn (): int => $this->attemptReserve($secondItem->order_id),
            );

            sort($codes);

            $this->assertSame([self::RESULT_SUCCESS, self::RESULT_INSUFFICIENT], $codes, "iteration {$iteration}");
            $this->assertSame(1, $stock->fresh()->reserved_quantity);
            $this->assertInvariant($stock->fresh());
        }
    }

    public function test_concurrent_adjustments_do_not_lose_updates(): void
    {
        $variant = $this->variant();
        $stock = $this->stock($variant, 'main', 10);
        $actor = User::factory()->staff()->create(['clerk_user_id' => 'staff_race']);

        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $stock->refresh()->update(['quantity' => 10, 'reserved_quantity' => 0]);

            $codes = $this->runChildren(
                fn (): int => $this->attemptAdjust($stock->id, 5, (string) Str::uuid(), $actor->id),
                fn (): int => $this->attemptAdjust($stock->id, -3, (string) Str::uuid(), $actor->id),
            );

            $this->assertSame([self::RESULT_SUCCESS, self::RESULT_SUCCESS], $codes, "iteration {$iteration}");
            $this->assertSame(12, $stock->fresh()->quantity);
        }
    }

    public function test_adjustment_and_reservation_races_preserve_invariants(): void
    {
        $variant = $this->variant();
        $stock = $this->stock($variant, 'main', 5);
        $actor = User::factory()->staff()->create(['clerk_user_id' => 'staff_race_2']);

        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $stock->refresh()->update(['quantity' => 5, 'reserved_quantity' => 0]);
            OrderItemInventoryAllocation::query()->delete();

            [, $item] = $this->orderWithItem($variant, 5);

            $codes = $this->runChildren(
                fn (): int => $this->attemptReserve($item->order_id),
                fn (): int => $this->attemptAdjust($stock->id, -2, (string) Str::uuid(), $actor->id, InventoryAdjustmentReason::DAMAGE),
            );

            $fresh = $stock->fresh();
            $this->assertSame(
                1,
                count(array_filter($codes, fn (int $code): bool => $code === self::RESULT_SUCCESS)),
                "iteration {$iteration}",
            );
            $this->assertInvariant($fresh);
            $this->assertFalse($fresh->quantity === 3 && $fresh->reserved_quantity === 5, "iteration {$iteration}");
        }
    }

    public function test_same_idempotency_key_race_adjusts_once(): void
    {
        $variant = $this->variant();
        $stock = $this->stock($variant, 'main', 10);
        $actor = User::factory()->staff()->create(['clerk_user_id' => 'staff_race_3']);

        for ($iteration = 0; $iteration < self::ITERATIONS; $iteration++) {
            $stock->refresh()->update(['quantity' => 10, 'reserved_quantity' => 0]);
            $key = (string) Str::uuid();

            $codes = $this->runChildren(
                fn (): int => $this->attemptAdjust($stock->id, 4, $key, $actor->id),
                fn (): int => $this->attemptAdjust($stock->id, 4, $key, $actor->id),
            );

            $this->assertSame([self::RESULT_SUCCESS, self::RESULT_SUCCESS], $codes, "iteration {$iteration}");
            $this->assertSame(14, $stock->fresh()->quantity, "iteration {$iteration}");
        }

        $this->assertSame(self::ITERATIONS, DB::table('idempotency_keys')->where('action', InventoryAdjustmentService::ACTION)->count());
    }

    /** @param callable(): int ...$workers */
    private function runChildren(callable ...$workers): array
    {
        $barrier = sys_get_temp_dir().'/inventory-barrier-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($barrier, 0777, true));

        $pids = [];

        foreach ($workers as $index => $worker) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'pcntl_fork failed');

            if ($pid === 0) {
                $code = self::RESULT_FAILURE;

                try {
                    DB::purge(self::CONNECTION);
                    DB::connection(self::CONNECTION)->selectOne('select 1');

                    touch($barrier.'/ready-'.$index);
                    $this->awaitBarrierRelease($barrier);

                    $code = $worker();
                } catch (\Throwable) {
                    $code = self::RESULT_FAILURE;
                }

                exit($code);
            }

            $pids[] = $pid;
        }

        $this->awaitWorkersReady($barrier, count($workers));
        touch($barrier.'/go');

        try {
            $codes = $this->collectChildResults($pids);
        } finally {
            $this->clearBarrier($barrier);
            DB::purge(self::CONNECTION);
        }

        return $codes;
    }

    /**
     * Waits for every worker with a bounded deadline; stalled workers are
     * killed and reaped before failing so a broken lock test cannot hang CI.
     *
     * @param  list<int>  $pids
     * @return list<int>
     */
    private function collectChildResults(array $pids): array
    {
        $deadline = microtime(true) + self::CHILD_TIMEOUT_SECONDS;
        $codes = [];
        $stalled = [];

        foreach ($pids as $pid) {
            $status = 0;
            $result = pcntl_waitpid($pid, $status, WNOHANG);

            while ($result === 0 && microtime(true) <= $deadline) {
                usleep(5_000);
                $result = pcntl_waitpid($pid, $status, WNOHANG);
            }

            if ($result === 0) {
                $stalled[] = $pid;
            } elseif ($result > 0) {
                $codes[] = pcntl_wexitstatus($status);
            }
        }

        if ($stalled !== []) {
            $this->terminateChildren($stalled);
            $this->fail('Concurrency workers did not complete within '.self::CHILD_TIMEOUT_SECONDS.'s.');
        }

        return $codes;
    }

    /** @param list<int> $pids */
    private function terminateChildren(array $pids): void
    {
        foreach ($pids as $pid) {
            posix_kill($pid, SIGKILL);
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    private function awaitBarrierRelease(string $barrier): void
    {
        $deadline = microtime(true) + 10;

        while (! file_exists($barrier.'/go')) {
            if (microtime(true) > $deadline) {
                throw new ConcurrentWorkerTimeout('Timed out waiting for the concurrency barrier release.');
            }

            usleep(200);
        }
    }

    private function awaitWorkersReady(string $barrier, int $count): void
    {
        $deadline = microtime(true) + 10;

        while (count(glob($barrier.'/ready-*') ?: []) < $count) {
            if (microtime(true) > $deadline) {
                return;
            }

            usleep(200);
        }
    }

    private function clearBarrier(string $barrier): void
    {
        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($barrier);
    }

    private function attemptReserve(int $orderId): int
    {
        try {
            app(InventoryAllocator::class)->reserve(Order::query()->findOrFail($orderId));

            return self::RESULT_SUCCESS;
        } catch (ApiException $exception) {
            return $exception->errorCode() === ApiErrorCode::INSUFFICIENT_STOCK
                ? self::RESULT_INSUFFICIENT
                : self::RESULT_FAILURE;
        }
    }

    private function attemptAdjust(
        int $stockId,
        int $delta,
        string $key,
        int $actorId,
        InventoryAdjustmentReason $reason = InventoryAdjustmentReason::CORRECTION,
    ): int {
        try {
            app(InventoryAdjustmentService::class)->adjust(
                ProductStock::query()->findOrFail($stockId),
                $delta,
                $reason,
                User::query()->findOrFail($actorId),
                $key,
                null,
            );

            return self::RESULT_SUCCESS;
        } catch (ApiException) {
            return self::RESULT_FAILURE;
        }
    }

    private function variant(): ProductVariant
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => ProductType::IN_STOCK,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    }

    private function stock(ProductVariant $variant, string $location, int $quantity): ProductStock
    {
        return ProductStock::factory()->forVariant($variant)->create([
            'warehouse_location' => $location,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);
    }

    /** @return array{0: Order, 1: OrderItem} */
    private function orderWithItem(ProductVariant $variant, int $quantity): array
    {
        $order = Order::factory()->create();
        $unitPrice = (int) $variant->price_amount;

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name' => 'Race item',
            'variant_name' => $variant->variant_name,
            'unit_price_amount' => $unitPrice,
            'quantity' => $quantity,
            'line_total_amount' => $unitPrice * $quantity,
        ]);

        return [$order, $item];
    }

    private function assertInvariant(ProductStock $stock): void
    {
        $this->assertGreaterThanOrEqual(0, $stock->quantity);
        $this->assertGreaterThanOrEqual(0, $stock->reserved_quantity);
        $this->assertLessThanOrEqual($stock->quantity, $stock->reserved_quantity);
    }
}
