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
use Tests\Support\RunsConcurrentWorkers;
use Tests\TestCase;

/**
 * ORD-014 concurrency gate on real MySQL/MariaDB.
 *
 *   DELIVERY_FEE_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/DeliveryFeeConcurrencyMysqlTest.php
 */
class DeliveryFeeConcurrencyMysqlTest extends TestCase
{
    use RunsConcurrentWorkers;

    private const CONNECTION = 'mysql_delivery_fee';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

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

        $results = $this->runConcurrentWorkers(
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

        $results = $this->runConcurrentWorkers(
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
}
