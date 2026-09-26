<?php

namespace Tests\Integration;

use App\Exceptions\Api\ApiException;
use App\Models\AuditEvent;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryFeeFinalizer;
use App\Support\DeliveryFeeStatus;
use App\Support\OrderIdentifier;
use App\Support\OrderStatus;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class DeliveryFeeConcurrencyBarrierTimeout extends RuntimeException {}

/**
 * ORD-014 concurrency gate on real MySQL/MariaDB.
 *
 *   DELIVERY_FEE_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/DeliveryFeeConcurrencyMysqlTest.php
 */
class DeliveryFeeConcurrencyMysqlTest extends TestCase
{
    private const CONNECTION = 'mysql_delivery_fee';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private const RESULT_FILE_PREFIX = '/result-';

    private string $previousDefaultConnection = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');
        $database = (string) getenv('DELIVERY_FEE_MYSQL_TEST_DATABASE');

        if ($database !== self::DISPOSABLE_DATABASE) {
            $this->markTestSkipped('requires disposable MySQL/MariaDB integration database (set DELIVERY_FEE_MYSQL_TEST_DATABASE='.self::DISPOSABLE_DATABASE.').');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl and posix extensions required.');
        }

        if (app()->environment('production')) {
            $this->fail('Refusing to run destructive concurrency tests in production.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(
            config('database.connections.mysql'),
            ['database' => $database],
        )]);
        config(['database.default' => self::CONNECTION]);

        DB::purge(self::CONNECTION);
        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
        Artisan::call('db:seed', ['--class' => RbacSeeder::class, '--database' => self::CONNECTION, '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        config(['database.default' => $this->previousDefaultConnection]);

        parent::tearDown();
    }

    public function test_concurrent_same_actor_same_key_replays_one_finalization(): void
    {
        [$order, $staff] = $this->pendingOrderAndStaff();
        $key = (string) Str::uuid();
        $orderIdentifier = OrderIdentifier::encode($order);

        $results = $this->runConcurrently(
            fn (): string => $this->finalize($staff->id, $orderIdentifier, 35_000, $key),
            fn (): string => $this->finalize($staff->id, $orderIdentifier, 35_000, $key),
        );

        $this->assertSame(['success', 'success'], $results);
        $this->assertFinalized($order->id, 35_000);
        $this->assertSame(1, AuditEvent::query()->where('action', 'DELIVERY_FEE_FINALIZED')->count());
        $this->assertSame(1, IdempotencyKey::query()->where('action', DeliveryFeeFinalizer::ACTION)->count());
    }

    public function test_concurrent_different_actors_and_keys_finalize_once(): void
    {
        [$order, $firstStaff] = $this->pendingOrderAndStaff();
        $secondStaff = User::factory()->staff()->create(['clerk_user_id' => 'fee_staff_2']);
        $orderIdentifier = OrderIdentifier::encode($order);

        $results = $this->runConcurrently(
            fn (): string => $this->finalize($firstStaff->id, $orderIdentifier, 35_000, (string) Str::uuid()),
            fn (): string => $this->finalize($secondStaff->id, $orderIdentifier, 36_000, (string) Str::uuid()),
        );

        sort($results);
        $this->assertSame(['error:INVALID_ORDER_TRANSITION', 'success'], $results);
        $this->assertSame(1, AuditEvent::query()->where('action', 'DELIVERY_FEE_FINALIZED')->count());
        $this->assertSame(2, IdempotencyKey::query()->where('action', DeliveryFeeFinalizer::ACTION)->count());

        $order = $order->fresh();
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $order->delivery_fee_status);
        $this->assertContains($order->delivery_fee_amount, [35_000, 36_000]);
        $this->assertSame($order->subtotal_amount + $order->delivery_fee_amount, $order->total_amount);
    }

    /** @return array{0: Order, 1: User} */
    private function pendingOrderAndStaff(): array
    {
        $order = Order::factory()->deliveryPending()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'fee_staff_1']);

        return [$order, $staff];
    }

    private function finalize(int $actorId, string $orderIdentifier, int $amount, string $key): string
    {
        try {
            app(DeliveryFeeFinalizer::class)->finalize(
                $orderIdentifier,
                $amount,
                User::query()->findOrFail($actorId),
                $key,
                null,
            );
        } catch (ApiException $exception) {
            return 'error:'.$exception->errorCode()->value;
        }

        return 'success';
    }

    private function assertFinalized(int $orderId, int $amount): void
    {
        $order = Order::query()->findOrFail($orderId);

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $order->delivery_fee_status);
        $this->assertSame($amount, $order->delivery_fee_amount);
        $this->assertSame($order->subtotal_amount + $amount, $order->total_amount);
    }

    /** @param callable(): string ...$workers */
    /** @return list<string> */
    private function runConcurrently(callable ...$workers): array
    {
        $barrier = sys_get_temp_dir().'/delivery-fee-barrier-'.bin2hex(random_bytes(8));
        mkdir($barrier, 0777, true);
        $pids = [];

        try {
            foreach ($workers as $index => $worker) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);

                if ($pid === 0) {
                    exit($this->runWorker($barrier, $index, $worker));
                }

                $pids[] = $pid;
            }

            $this->awaitReady($barrier, count($workers));
            touch($barrier.'/go');
            $this->reapWorkers($pids, $barrier);

            $results = [];
            foreach (array_keys($workers) as $index) {
                $results[] = $this->resultFor($barrier, $index);
            }

            return $results;
        } finally {
            $this->terminateWorkers($pids);
            $this->destroyBarrier($barrier);
            DB::purge(self::CONNECTION);
        }
    }

    private function runWorker(string $barrier, int $index, callable $worker): int
    {
        try {
            DB::purge(self::CONNECTION);
            DB::connection(self::CONNECTION)->selectOne('select 1');
            touch($barrier.'/ready-'.$index);
            $this->awaitBarrier($barrier);
            file_put_contents($barrier.self::RESULT_FILE_PREFIX.$index, (string) $worker());

            return 0;
        } catch (\Throwable $exception) {
            @file_put_contents($barrier.self::RESULT_FILE_PREFIX.$index, 'crash:'.$exception::class.':'.$exception->getMessage());

            return 1;
        }
    }

    /** @param list<int> $pids */
    private function reapWorkers(array $pids, string $barrier): void
    {
        $deadline = microtime(true) + 30;

        foreach ($pids as $index => $pid) {
            $status = 0;
            $result = pcntl_waitpid($pid, $status, WNOHANG);

            while ($result === 0 && microtime(true) <= $deadline) {
                usleep(5_000);
                $result = pcntl_waitpid($pid, $status, WNOHANG);
            }

            if ($result === 0) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
                $this->fail('Concurrency worker did not complete in time.');
            }

            $this->assertTrue(
                pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
                'Concurrency worker failed: '.$this->resultFor($barrier, $index),
            );
        }
    }

    private function resultFor(string $barrier, int $index): string
    {
        $file = $barrier.self::RESULT_FILE_PREFIX.$index;

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    /** @param list<int> $pids */
    private function terminateWorkers(array $pids): void
    {
        foreach ($pids as $pid) {
            $status = 0;

            if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
        }
    }

    private function destroyBarrier(string $barrier): void
    {
        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($barrier);
    }

    private function awaitBarrier(string $barrier): void
    {
        $deadline = microtime(true) + 10;

        while (! is_file($barrier.'/go')) {
            if (microtime(true) > $deadline) {
                throw new DeliveryFeeConcurrencyBarrierTimeout('Barrier timeout.');
            }

            usleep(200);
        }
    }

    private function awaitReady(string $barrier, int $count): void
    {
        $deadline = microtime(true) + 10;

        while (count(glob($barrier.'/ready-*') ?: []) < $count) {
            if (microtime(true) > $deadline) {
                throw new DeliveryFeeConcurrencyBarrierTimeout('Workers did not become ready in time.');
            }

            usleep(200);
        }
    }
}
