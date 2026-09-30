<?php

namespace Tests\Feature;

use App\Exceptions\Api\ApiException;
use App\Exceptions\DeliveryCheckoutUnsupportedException;
use App\Models\CartItem;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemInventoryAllocation;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Checkout\CheckoutCommand;
use App\Services\Checkout\CheckoutTransaction;
use App\Services\IdempotencyService;
use App\Support\CartStatus;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderActorType;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

class CheckoutTransactionTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    public function test_pickup_checkout_commits_the_full_atomic_boundary(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 25_000);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 4);

        $outcome = $this->checkout()->execute(CheckoutCommand::pickup($customer, 'checkout-key'));

        $this->assertSame(201, $outcome->status);
        $this->assertFalse($outcome->replayed);

        $order = Order::query()->sole();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertSame(FulfillmentType::PICKUP, $order->fulfillment_type);
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $order->delivery_fee_status);
        $this->assertSame(0, $order->delivery_fee_amount);
        $this->assertSame(100_000, $order->subtotal_amount);
        $this->assertSame(100_000, $order->total_amount);
        $this->assertNull($order->delivery_address);
        $this->assertSame('TZS', $order->currency);
        $this->assertMatchesRegularExpression('/^OD-[A-Z0-9]{5}$/', (string) $order->order_reference);

        $item = OrderItem::query()->sole();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($variant->id, $item->variant_id);
        $this->assertSame((string) $variant->sku, (string) $item->sku);
        $this->assertSame($product->name, $item->name);
        $this->assertSame(4, $item->quantity);
        $this->assertSame(25_000, $item->unit_price_amount);
        $this->assertSame(100_000, $item->line_total_amount);

        $this->assertSame(4, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(10, $variant->fresh()->stocks->first()->quantity);
        $this->assertSame(4, OrderItemInventoryAllocation::query()->sole()->quantity);

        $history = OrderStatusHistory::query()->sole();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $history->to_status);
        $this->assertNull($history->from_status);
        $this->assertSame(OrderActorType::SYSTEM, $history->actor_type);
        $this->assertNull($history->actor_id);

        $this->assertSame(CartStatus::ACTIVE, $cart->fresh()->status);
        $this->assertSame(0, $cart->fresh()->items()->count());

        $this->assertSame('ord_'.$order->public_id, $outcome->body['order_id']);
        $this->assertSame(100_000, $outcome->body['subtotal']['amount']);
        $this->assertSame(0, $outcome->body['delivery_fee']['amount']);
        $this->assertSame(100_000, $outcome->body['total']['amount']);
        $this->assertNull($outcome->body['payment']);
    }

    public function test_checkout_uses_current_variant_price_not_the_cart_projection(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 25_000);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $variant->forceFill(['price_amount' => 30_000])->save();

        $this->checkout()->execute(CheckoutCommand::pickup($customer, 'price-drift'));

        $item = OrderItem::query()->sole();
        $this->assertSame(30_000, $item->unit_price_amount);
        $this->assertSame(60_000, $item->line_total_amount);
        $this->assertSame(60_000, Order::query()->sole()->subtotal_amount);
    }

    public function test_checkout_rejects_an_inactive_product_without_mutation(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $product->forceFill(['is_active' => false])->save();

        $this->expectApiError('PRODUCT_UNAVAILABLE', fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k')));

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $cart->fresh()->items()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
    }

    public function test_checkout_rejects_an_inactive_variant(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $variant->forceFill(['is_active' => false])->save();

        $this->expectApiError('INVALID_PRODUCT_VARIANT', fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k')));

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_rejects_a_made_to_order_product(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $product->forceFill(['product_type' => 'MADE_TO_ORDER'])->save();

        $this->expectApiError('PRODUCT_NOT_PURCHASABLE', fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k')));

        $this->assertSame(0, Order::query()->count());
    }

    public function test_insufficient_stock_rolls_back_everything(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 1);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 5);

        $this->expectApiError('INSUFFICIENT_STOCK', fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k')));

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(5, $cart->fresh()->items()->sum('quantity'));
        $this->assertSame(0, IdempotencyKey::query()->count());
    }

    public function test_missing_and_empty_active_cart_are_rejected(): void
    {
        $withoutCart = $this->cartCustomer('customer_no_cart');
        $this->expectApiError('CART_INVALID', fn () => $this->checkout()->execute(CheckoutCommand::pickup($withoutCart, 'k1')));

        $empty = $this->cartCustomer('customer_empty');
        $this->activeCartFor($empty);
        $this->expectApiError('CART_INVALID', fn () => $this->checkout()->execute(CheckoutCommand::pickup($empty, 'k2')));
    }

    public function test_failure_after_order_insert_rolls_back(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        Event::listen('eloquent.creating: '.OrderItem::class, fn () => throw new RuntimeException('forced'));

        $this->runFailing(fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k')));

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(1, $cart->fresh()->items()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(0, IdempotencyKey::query()->count());
    }

    public function test_failure_after_reservation_rolls_back_stock_and_allocations(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 3);

        Event::listen('eloquent.creating: '.OrderStatusHistory::class, fn () => throw new RuntimeException('forced'));

        $this->runFailing(fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k')));

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(3, $cart->fresh()->items()->sum('quantity'));
        $this->assertSame(0, IdempotencyKey::query()->count());
    }

    public function test_failure_during_cart_clear_rolls_back(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        Event::listen('eloquent.deleted: '.CartItem::class, fn () => throw new RuntimeException('forced'));

        $this->runFailing(fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k')));

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(1, $cart->fresh()->items()->count());
        $this->assertSame(0, IdempotencyKey::query()->count());
    }

    public function test_same_key_replays_the_original_201_after_the_cart_is_cleared(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $command = CheckoutCommand::pickup($customer, 'replay-key');
        $first = $this->checkout()->execute($command);
        $second = $this->checkout()->execute($command);

        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame(201, $first->status);
        $this->assertSame(201, $second->status);
        $this->assertSame($first->body['order_reference'], $second->body['order_reference']);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(CartStatus::ACTIVE, $cart->fresh()->status);
    }

    public function test_different_key_checkout_of_unchanged_cart_fails_after_the_first_clears_it(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $this->checkout()->execute(CheckoutCommand::pickup($customer, 'generation-1'));

        $this->expectApiError('CART_INVALID', fn () => $this->checkout()->execute(CheckoutCommand::pickup($customer, 'generation-2')));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_checkout_after_the_customer_refills_the_active_cart_creates_a_new_order(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $this->checkout()->execute(CheckoutCommand::pickup($customer, 'generation-1'));
        $this->assertSame(0, $cart->fresh()->items()->count());

        // The Cart stays ACTIVE; new items are a new contents generation.
        [$secondProduct, $secondVariant] = $this->stockedProduct(quantity: 10);
        $this->itemFor($cart, $secondProduct, $secondVariant, 2);

        $second = $this->checkout()->execute(CheckoutCommand::pickup($customer, 'generation-2'));

        $this->assertSame(201, $second->status);
        $this->assertFalse($second->replayed);
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(0, $cart->fresh()->items()->count());
        $this->assertSame(CartStatus::ACTIVE, $cart->fresh()->status);
    }

    public function test_same_key_with_a_changed_intent_conflicts(): void
    {
        $customer = $this->cartCustomer();
        $idempotency = app(IdempotencyService::class);

        $idempotency->execute(
            $customer,
            CheckoutTransaction::ACTION,
            'changed',
            ['fulfillment_type' => 'PICKUP', 'delivery_address' => null],
            fn (): array => ['ok' => true],
            201,
        );

        $this->expectApiError('DUPLICATE_OPERATION', fn () => $idempotency->execute(
            $customer,
            CheckoutTransaction::ACTION,
            'changed',
            ['fulfillment_type' => 'PICKUP', 'delivery_address' => ['city' => 'Dodoma']],
            fn (): array => ['ok' => true],
            201,
        ));
    }

    public function test_different_customers_reuse_the_same_key_as_separate_scopes(): void
    {
        [$productA, $variantA] = $this->stockedProduct(quantity: 10);

        $first = $this->cartCustomer('scope_a');
        $firstCart = $this->activeCartFor($first);
        $this->itemFor($firstCart, $productA, $variantA, 1);

        [$productB, $variantB] = $this->stockedProduct(quantity: 10);
        $second = $this->cartCustomer('scope_b');
        $secondCart = $this->activeCartFor($second);
        $this->itemFor($secondCart, $productB, $variantB, 1);

        $firstOutcome = $this->checkout()->execute(CheckoutCommand::pickup($first, 'shared-key'));
        $secondOutcome = $this->checkout()->execute(CheckoutCommand::pickup($second, 'shared-key'));

        $this->assertFalse($firstOutcome->replayed);
        $this->assertFalse($secondOutcome->replayed);
        $this->assertSame(2, Order::query()->count());
        $this->assertNotSame($firstOutcome->body['order_reference'], $secondOutcome->body['order_reference']);
    }

    public function test_staff_and_admin_cannot_execute_customer_checkout(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->admin()->create()] as $actor) {
            $this->expectApiError('FORBIDDEN', fn () => $this->checkout()->execute(CheckoutCommand::pickup($actor, 'k')));
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_delivery_checkout_is_rejected_before_any_mutation(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        try {
            $this->checkout()->execute(CheckoutCommand::delivery($customer, ['city' => 'Dar es Salaam'], 'k'));
            $this->fail('Expected DELIVERY checkout to hit the persistence blocker.');
        } catch (DeliveryCheckoutUnsupportedException) {
            // expected
        }

        // The guard is a pre-claim precondition, so no idempotency row is written.
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, IdempotencyKey::query()->count());
        $this->assertSame(1, $cart->fresh()->items()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
    }

    public function test_order_item_snapshot_is_immutable_after_catalog_changes(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 25_000);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $this->checkout()->execute(CheckoutCommand::pickup($customer, 'k'));

        $before = OrderItem::query()->sole();
        $originalName = $before->name;
        $originalSku = (string) $before->sku;

        $product->forceFill(['name' => 'Renamed Product'])->save();
        $variant->forceFill(['variant_name' => 'Renamed Variant', 'sku' => 'NEW-SKU', 'price_amount' => 99_000])->save();

        $after = OrderItem::query()->sole();
        $this->assertSame($originalName, $after->name);
        $this->assertSame($originalSku, (string) $after->sku);
        $this->assertSame(25_000, $after->unit_price_amount);
    }

    private function checkout(): CheckoutTransaction
    {
        return app(CheckoutTransaction::class);
    }

    private function runFailing(callable $callback): void
    {
        try {
            DB::transaction(fn () => $callback());
        } catch (RuntimeException) {
            // expected forced failure
        }
    }

    private function expectApiError(string $code, callable $callback): void
    {
        try {
            // A real Checkout runs in one transaction; wrapping here restores
            // production rollback semantics under the RefreshDatabase wrapper.
            DB::transaction(fn () => $callback());
            $this->fail("Expected ApiException {$code}.");
        } catch (ApiException $exception) {
            $this->assertSame($code, $exception->errorCode()->value);
        }
    }
}
