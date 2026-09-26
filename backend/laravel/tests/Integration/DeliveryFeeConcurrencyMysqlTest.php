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
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentWorkers;
use Tests\Support\UsesDisposableMysqlDatabase;
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
    use UsesDisposableMysqlDatabase;

    protected function seedDisposableMysqlDatabase(): void
    {
        $this->seed(RbacSeeder::class);
    }

    protected function databaseConnectionName(): string
    {
        return 'mysql_delivery_fee';
    }

    protected function databaseEnvironmentVariable(): string
    {
        return 'DELIVERY_FEE_MYSQL_TEST_DATABASE';
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
        $this->assertSame(1, IdempotencyKey::query()->where('action', DeliveryFeeFinalizer::ACTION)->count());

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
