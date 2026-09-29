<?php

namespace App\Services\Checkout;

use App\Exceptions\Api\ApiException;
use App\Exceptions\DeliveryCheckoutUnsupportedException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartItemEligibility;
use App\Services\Cart\CartItemInvalidReason;
use App\Services\IdempotencyService;
use App\Services\IdempotentOutcome;
use App\Services\Inventory\InventoryAllocator;
use App\Support\ApiErrorCode;
use App\Support\CartStatus;
use App\Support\FulfillmentType;
use App\Support\OrderActorType;
use App\Support\OrderIdentifier;
use App\Support\OrderStatus;
use App\Support\ReferenceGenerator;
use App\Support\RoleName;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Authoritative CHK-001 Checkout transaction boundary. The shared
 * IdempotencyService owns the single outer transaction, so every business
 * mutation here (Cart lock/snapshot, Order aggregate, reservation, history,
 * Cart clear) and the stored 201 outcome either commit together or roll back
 * together. PICKUP is fully supported; DELIVERY fails before any mutation
 * because Phase 7.4 billing-snapshot persistence remains blocked.
 */
final class CheckoutTransaction
{
    public const ACTION = 'CHK-001';

    private const SUCCESS_STATUS = 201;

    private const REFERENCE_LENGTH = 5;

    private const MAX_REFERENCE_ATTEMPTS = 3;

    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly InventoryAllocator $allocator,
    ) {}

    public function execute(CheckoutCommand $command): IdempotentOutcome
    {
        $this->assertCustomer($command->customer);
        $this->assertFulfillmentSupported($command);

        $intent = [
            'fulfillment_type' => $command->fulfillmentType->value,
            'delivery_address' => $command->deliveryAddress,
        ];

        return $this->idempotency->execute(
            $command->customer,
            self::ACTION,
            $command->idempotencyKey,
            $intent,
            fn (): array => $this->perform($command),
            self::SUCCESS_STATUS,
        );
    }

    /** @return array<string, mixed> */
    private function perform(CheckoutCommand $command): array
    {
        $cart = $this->lockActiveCart($command->customer);
        $items = $cart->items()->orderBy('id')->lockForUpdate()->get();

        if ($items->isEmpty()) {
            throw new ApiException(ApiErrorCode::CART_INVALID, 'The cart is empty.', 422);
        }

        $lines = $this->resolveLines($items);
        $subtotal = OrderTotalsCalculator::calculateSubtotal(
            array_map(static fn (CheckoutLine $line): OrderLineAmount => $line->amount, $lines),
        );

        // PICKUP branch projection owns fee=0/FINALIZED/address=null; the
        // canonical totals calculator owns the arithmetic.
        $state = PickupFulfillmentState::fromInput(['fulfillment_type' => FulfillmentType::PICKUP->value]);
        $attributes = $state->orderPersistenceAttributesForSubtotal($subtotal);

        $order = $this->persistOrder($command->customer, $attributes);
        $this->persistItems($order, $lines);
        $this->allocator->reserve($order);
        $this->persistHistory($order);
        $this->clearCart($cart);

        return $this->checkoutBody($order, $attributes);
    }

    private function lockActiveCart(User $customer): Cart
    {
        $cart = Cart::query()
            ->where('user_id', $customer->getKey())
            ->where('status', CartStatus::ACTIVE)
            ->lockForUpdate()
            ->first();

        if ($cart === null) {
            throw new ApiException(ApiErrorCode::CART_INVALID, 'The cart is not available for checkout.', 422);
        }

        return $cart;
    }

    /**
     * Revalidates each locked Cart line against current authoritative Product
     * and Variant records and snapshots the current unit price.
     *
     * @param  Collection<int, CartItem>  $items
     * @return list<CheckoutLine>
     */
    private function resolveLines(Collection $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $product = Product::query()->withTrashed()->with('category')->find($item->product_id);

            if ($product === null) {
                throw CartItemInvalidReason::PRODUCT_MISSING->toApiException();
            }

            $variant = $item->variant_id === null ? null : ProductVariant::query()->find($item->variant_id);
            $reason = CartItemEligibility::requirementReason($product, $variant);

            if ($reason !== null) {
                throw $reason->toApiException();
            }

            if ($variant === null) {
                throw CartItemInvalidReason::VARIANT_REQUIRED->toApiException();
            }

            $lines[] = new CheckoutLine(
                amount: OrderTotalsCalculator::calculateLine($variant->price_amount, $item->quantity),
                productId: (int) $product->getKey(),
                variantId: (int) $variant->getKey(),
                sku: (string) $variant->sku,
                name: (string) $product->name,
                variantName: $variant->variant_name,
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persistOrder(User $customer, array $attributes): Order
    {
        for ($attempt = 1; ; $attempt++) {
            $order = new Order;
            $order->forceFill([
                'customer_id' => $customer->getKey(),
                'order_reference' => ReferenceGenerator::generate(Order::REFERENCE_PREFIX, self::REFERENCE_LENGTH),
                ...$attributes,
            ]);

            try {
                $order->save();

                return $order;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_REFERENCE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  list<CheckoutLine>  $lines
     */
    private function persistItems(Order $order, array $lines): void
    {
        foreach ($lines as $line) {
            OrderItem::query()->create([
                'order_id' => $order->getKey(),
                'product_id' => $line->productId,
                'variant_id' => $line->variantId,
                'sku' => $line->sku,
                'name' => $line->name,
                'variant_name' => $line->variantName,
                'unit_price_amount' => $line->amount->unitPriceAmount,
                'quantity' => $line->amount->quantity,
                'line_total_amount' => $line->amount->lineTotalAmount,
            ]);
        }
    }

    private function persistHistory(Order $order): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => null,
            'to_status' => OrderStatus::PENDING_PAYMENT,
            'actor_type' => OrderActorType::SYSTEM,
            'actor_id' => null,
            'occurred_at' => now(),
        ]);
    }

    private function clearCart(Cart $cart): void
    {
        CartItem::query()->where('cart_id', $cart->getKey())->get()->each->delete();

        $cart->touch();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function checkoutBody(Order $order, array $attributes): array
    {
        return [
            'order_id' => OrderIdentifier::encode($order),
            'order_reference' => (string) $order->order_reference,
            'status' => $attributes['status'],
            'fulfillment_type' => $attributes['fulfillment_type'],
            'delivery_address' => $attributes['delivery_address'],
            'subtotal' => $this->money((int) $attributes['subtotal_amount']),
            'delivery_fee' => $attributes['delivery_fee_amount'] === null ? null : $this->money((int) $attributes['delivery_fee_amount']),
            'delivery_fee_status' => $attributes['delivery_fee_status'],
            'total' => $this->money((int) $attributes['total_amount']),
            'currency' => $attributes['currency'],
            'payment' => null,
        ];
    }

    /** @return array{amount: int, currency: string} */
    private function money(int $amount): array
    {
        return ['amount' => $amount, 'currency' => Order::CURRENCY_TZS];
    }

    private function assertCustomer(User $customer): void
    {
        if (! $customer->hasRole(RoleName::CUSTOMER->value)
            || $customer->hasAnyRole([RoleName::STAFF->value, RoleName::ADMIN->value])) {
            throw new ApiException(ApiErrorCode::FORBIDDEN, 'Checkout is available to customers only.', 403);
        }
    }

    private function assertFulfillmentSupported(CheckoutCommand $command): void
    {
        if ($command->fulfillmentType !== FulfillmentType::PICKUP) {
            throw new DeliveryCheckoutUnsupportedException(
                'DELIVERY checkout persistence is blocked pending the billing snapshot model.',
            );
        }
    }
}
