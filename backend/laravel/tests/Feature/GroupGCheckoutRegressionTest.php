<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemInventoryAllocation;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\GuestCartTransport;
use App\Services\Checkout\CheckoutCommand;
use App\Services\Checkout\CheckoutTransaction;
use App\Services\Checkout\OrderTotalsCalculator;
use App\Services\Inventory\InventoryAllocator;
use App\Support\CartStatus;
use App\Support\OrderActorType;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

/**
 * Phase 7.9 consolidated Group G regression: representative cross-component
 * protection for the frozen CHK-001 contract and the Group G invariants so
 * later Groups cannot silently break Checkout. Detailed unit coverage stays in
 * the specialized Phase 7.2-7.8 test files.
 */
class GroupGCheckoutRegressionTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    private const URL = '/api/v1/checkout';

    protected function setUp(): void
    {
        parent::setUp();
        config(['checkout.route_enabled' => true]);
    }

    public function test_active_customer_pickup_checkout_is_end_to_end_correct(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 25_000);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 4);

        $response = $this->checkout($customer, ['fulfillment_type' => 'PICKUP'])->assertStatus(201);

        $response->assertJsonPath('data.status', 'PENDING_PAYMENT')
            ->assertJsonPath('data.fulfillment_type', 'PICKUP')
            ->assertJsonPath('data.delivery_address', null)
            ->assertJsonPath('data.delivery_fee.amount', 0)
            ->assertJsonPath('data.delivery_fee_status', 'FINALIZED')
            ->assertJsonPath('data.total.amount', 100_000)
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.payment', null);

        $this->assertStringStartsWith('ord_', (string) $response->json('data.order_id'));
        $this->assertMatchesRegularExpression('/^OD-[A-Z0-9]{5}$/', (string) $response->json('data.order_reference'));

        $order = Order::query()->sole();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertSame(100_000, $order->subtotal_amount);
        $this->assertSame(0, $order->delivery_fee_amount);
        $this->assertSame(100_000, $order->total_amount);

        $item = OrderItem::query()->sole();
        $this->assertSame(25_000, $item->unit_price_amount);
        $this->assertSame(4, $item->quantity);
        $this->assertSame(100_000, $item->line_total_amount);
        $this->assertSame((string) $variant->sku, (string) $item->sku);

        $this->assertSame(4, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(10, $variant->fresh()->stocks->first()->quantity);
        $this->assertSame(4, OrderItemInventoryAllocation::query()->sole()->quantity);

        $history = OrderStatusHistory::query()->sole();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $history->to_status);
        $this->assertSame(OrderActorType::SYSTEM, $history->actor_type);

        $this->assertSame(CartStatus::ACTIVE, $cart->fresh()->status);
        $this->assertSame(0, $cart->fresh()->items()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_guest_credential_alone_cannot_checkout(): void
    {
        $this->withHeaders([GuestCartTransport::HEADER => (string) Str::uuid()])
            ->postJson(self::URL, ['fulfillment_type' => 'PICKUP'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_staff_and_admin_cannot_checkout(): void
    {
        foreach ([
            User::factory()->staff()->create(['clerk_user_id' => 'g_staff']),
            User::factory()->admin()->create(['clerk_user_id' => 'g_admin']),
        ] as $actor) {
            $this->checkout($actor, ['fulfillment_type' => 'PICKUP'])
                ->assertStatus(403)
                ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_server_controlled_and_unknown_fields_are_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        foreach ([
            'cart_id' => 'cart_x', 'user_id' => 1, 'customer_id' => 1,
            'subtotal' => 1, 'total' => 1, 'unit_price' => 1, 'line_total' => 1,
            'currency' => 'TZS', 'delivery_fee' => 1, 'delivery_fee_status' => 'PENDING',
            'status' => 'PAID', 'payment' => [], 'payment_status' => 'PAID',
            'order_reference' => 'OD-ABCDE', 'billing_address' => [], 'saved_address_id' => 'x',
        ] as $field => $value) {
            $this->checkout($customer, ['fulfillment_type' => 'PICKUP', $field => $value])
                ->assertStatus(422)
                ->assertJsonPath('errors.0.code', 'INVALID_VALUE');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_city_is_canonical_and_region_is_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $address = ['recipient_name' => 'Asha', 'phone' => '+255700000001', 'address_line' => 'Street', 'city' => 'Dar es Salaam'];

        // canonical city is accepted by validation and reaches the persistence
        // blocker, which is contract-mapped (never a generic 500).
        $this->checkout($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => $address])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'BUSINESS_RULE_VIOLATION');

        // region substitute and city+region are rejected
        foreach ([
            ['recipient_name' => 'Asha', 'phone' => '+255700000001', 'address_line' => 'Street', 'region' => 'Dar es Salaam'],
            [...$address, 'region' => 'Dar es Salaam'],
        ] as $invalid) {
            $this->checkout($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => $invalid])->assertStatus(422);
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_uses_current_variant_price_not_the_cart_projection(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 25_000);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $variant->forceFill(['price_amount' => 30_000])->save();

        $this->checkout($customer, ['fulfillment_type' => 'PICKUP'])->assertStatus(201);

        $item = OrderItem::query()->sole();
        $this->assertSame(30_000, $item->unit_price_amount);
        $this->assertSame(60_000, $item->line_total_amount);
        $this->assertSame(60_000, Order::query()->sole()->subtotal_amount);
    }

    public function test_pending_delivery_fee_is_distinct_from_zero_fee(): void
    {
        $pending = OrderTotalsCalculator::forDeliveryPending(170_000);
        $finalizedZero = OrderTotalsCalculator::forDeliveryFinalized(170_000, 0);

        $this->assertNull($pending->deliveryFeeAmount);
        $this->assertSame(0, $finalizedZero->deliveryFeeAmount);
        $this->assertFalse($pending->isFinal);
        $this->assertTrue($finalizedZero->isFinal);
        $this->assertSame($pending->totalAmount, $finalizedZero->totalAmount);
    }

    public function test_same_key_replays_the_original_201_after_the_cart_is_cleared(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $key = (string) Str::uuid();
        $first = $this->checkout($customer, ['fulfillment_type' => 'PICKUP'], $key)->assertStatus(201);
        $second = $this->checkout($customer, ['fulfillment_type' => 'PICKUP'], $key)->assertStatus(201);

        // The replay must return the exact original contracted snapshot...
        $this->assertSame($first->json('data'), $second->json('data'));

        // ...and must not duplicate any business effect.
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, OrderItem::query()->count());
        $this->assertSame(1, OrderItemInventoryAllocation::query()->count());
        $this->assertSame(1, OrderStatusHistory::query()->count());
        $this->assertSame(1, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_same_key_changed_intent_conflicts(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $key = (string) Str::uuid();
        $this->checkout($customer, ['fulfillment_type' => 'PICKUP'], $key)->assertStatus(201);

        $this->checkout($customer, [
            'fulfillment_type' => 'DELIVERY',
            'delivery_address' => ['recipient_name' => 'Asha', 'phone' => '+255700000001', 'address_line' => 'Street', 'city' => 'Dar es Salaam'],
        ], $key)->assertStatus(409)->assertJsonPath('errors.0.code', 'DUPLICATE_OPERATION');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_rollback_after_reservation_leaves_no_side_effects(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 3);

        $historyAttempted = false;
        Event::listen('eloquent.creating: '.OrderStatusHistory::class, function () use (&$historyAttempted): never {
            $historyAttempted = true;

            throw new RuntimeException('forced-history-failure');
        });

        try {
            DB::transaction(fn () => $this->transaction()->execute(CheckoutCommand::pickup($customer, 'rollback')));
            $this->fail('Expected the forced failure at status-history creation.');
        } catch (RuntimeException $exception) {
            // Only the injected failure is acceptable; anything thrown earlier
            // (before reservation) must not satisfy this rollback proof.
            $this->assertSame('forced-history-failure', $exception->getMessage());
        }

        $this->assertTrue($historyAttempted, 'Checkout must reach history creation (after reservation) before failing.');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(3, $cart->fresh()->items()->sum('quantity'));
    }

    public function test_last_unit_checkout_leaves_zero_available(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 1);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $this->transaction()->execute(CheckoutCommand::pickup($customer, 'last-unit'));

        $stock = $variant->fresh()->stocks->first();
        $this->assertSame(1, $stock->quantity);
        $this->assertSame(1, $stock->reserved_quantity);
        $this->assertSame(0, $stock->available_quantity);
    }

    public function test_exact_allocation_rows_support_later_release(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $this->transaction()->execute(CheckoutCommand::pickup($customer, 'trace'));

        $order = Order::query()->sole();
        $allocation = OrderItemInventoryAllocation::query()->sole();
        $this->assertSame($variant->fresh()->stocks->first()->id, $allocation->product_stock_id);
        $this->assertSame(2, $allocation->quantity);

        app(InventoryAllocator::class)->release($order);

        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());
    }

    public function test_checkout_snapshot_preserves_a_null_variant_name(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        // The frozen product_variants.variant_name column is NOT NULL, so a null
        // source cannot be seeded. Simulate a nullable source name at retrieval
        // (no schema change) and prove the Checkout snapshot persists null
        // rather than coercing it to "".
        ProductVariant::retrieved(fn (ProductVariant $loaded) => $loaded->variant_name = null);

        $this->transaction()->execute(CheckoutCommand::pickup($customer, 'nullable-name'));

        $this->assertNull(OrderItem::query()->sole()->fresh()->variant_name);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkout(User $customer, array $payload, ?string $key = null): TestResponse
    {
        RateLimiter::clear(md5('checkoutuser:'.$customer->getAuthIdentifier()));

        return $this->withHeaders($this->authenticateAs($customer) + ['Idempotency-Key' => $key ?? (string) Str::uuid()])
            ->postJson(self::URL, $payload);
    }

    private function transaction(): CheckoutTransaction
    {
        return app(CheckoutTransaction::class);
    }
}
