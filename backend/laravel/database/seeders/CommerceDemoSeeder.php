<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\EnquiryCategory;
use App\Support\NotificationType;
use App\Support\OrderActorType;
use App\Support\OrderStatus;
use App\Support\PaymentStatus;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Disposable demo commerce data for local development (fresh database only).
 *
 * Skips silently when orders already exist. Requires DevelopmentUserSeeder
 * and CatalogSeeder to run first (handled by DemoSeeder). Never run against
 * production.
 */
class CommerceDemoSeeder extends Seeder
{
    private const DELIVERY_FEE = 15000;

    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $customer = User::where('email', 'customer@example.com')->firstOrFail();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();

        $this->seedCarts($customer);
        $this->seedCompletedPickup($customer, $staff);
        $completedDelivery = $this->seedCompletedDelivery($customer, $staff);
        $pending = $this->seedPendingOrder($customer);
        $this->seedCancelledOrder($customer);
        $this->seedRequests($customer);
        $this->seedEnquiries($customer, $pending);
        $this->seedNotifications($customer, $staff, $completedDelivery);
    }

    private function variant(string $sku): ProductVariant
    {
        return ProductVariant::where('sku', $sku)->firstOrFail();
    }

    private function seedCarts(User $customer): void
    {
        $grey = $this->variant('NORDIC-SOFA-GREY');
        $dining = $this->variant('RUSTIC-TABLE-OAK');

        $active = Cart::factory()->for($customer)->active()->create();
        CartItem::factory()->for($active)->for($grey->product)->for($grey, 'variant')->create(['quantity' => 1]);
        CartItem::factory()->for($active)->for($dining->product)->for($dining, 'variant')->create(['quantity' => 1]);

        $historic = Cart::factory()->for($customer)->inactive()->create();
        CartItem::factory()->for($historic)->for($grey->product)->for($grey, 'variant')->create(['quantity' => 2]);

        $guest = Cart::factory()->guestOwned()->create();
        CartItem::factory()->for($guest)->for($dining->product)->for($dining, 'variant')->create(['quantity' => 1]);
    }

    /**
     * @param  list<array{0: ProductVariant, 1: int}>  $lines
     */
    private function lineSubtotal(array $lines): int
    {
        $subtotal = 0;

        foreach ($lines as [$variant, $quantity]) {
            $subtotal += ((int) $variant->price_amount) * $quantity;
        }

        return $subtotal;
    }

    /**
     * @return array{delivery_fee_amount: int|null, total_amount: int}
     */
    private function orderAmounts(string $orderState, int $subtotal): array
    {
        return match ($orderState) {
            'pickup' => ['delivery_fee_amount' => 0, 'total_amount' => $subtotal],
            'deliveryPending' => ['delivery_fee_amount' => null, 'total_amount' => $subtotal],
            'deliveryFinalized' => ['delivery_fee_amount' => self::DELIVERY_FEE, 'total_amount' => $subtotal + self::DELIVERY_FEE],
            default => throw new InvalidArgumentException("Unknown demo order state [{$orderState}]."),
        };
    }

    /**
     * @param  list<array{0: ProductVariant, 1: int}>  $lines
     */
    private function createPricedOrder(User $customer, string $orderState, array $lines): Order
    {
        $subtotal = $this->lineSubtotal($lines);
        $amounts = $this->orderAmounts($orderState, $subtotal);
        $products = Product::query()
            ->whereKey(array_unique(array_map(
                fn (array $line): int => $line[0]->product_id,
                $lines,
            )))
            ->get()
            ->keyBy('id');

        $order = Order::factory()->for($customer, 'customer')->{$orderState}()->create(array_merge([
            'subtotal_amount' => $subtotal,
            'recipient_name' => 'Amina Customer',
            'recipient_phone' => '+255700000001',
        ], $amounts));

        foreach ($lines as [$variant, $quantity]) {
            $product = $products->get($variant->product_id);
            if ($product === null) {
                throw new InvalidArgumentException("Product [{$variant->product_id}] was not found for demo order.");
            }

            OrderItem::factory()->for($order)->for($product)->create([
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $product->name,
                'variant_name' => $variant->variant_name,
                'unit_price_amount' => $variant->price_amount,
                'quantity' => $quantity,
                'line_total_amount' => ((int) $variant->price_amount) * $quantity,
            ]);
        }

        $order->refresh();

        return $order;
    }

    /**
     * @param  list<array{0: ?OrderStatus, 1: OrderStatus, 2: OrderActorType, 3: ?User, 4: int}>  $steps
     */
    private function seedHistory(Order $order, Carbon $base, array $steps): void
    {
        foreach ($steps as [$from, $to, $actorType, $actor, $hours]) {
            $event = OrderStatusHistory::factory()->for($order);

            $event = $from === null
                ? $event->initial()
                : $event->transition($from, $to);

            $event = $event->at($base->copy()->addHours($hours));

            if ($actor !== null) {
                $event = $event->byUser($actor, $actorType);
            }

            $event->create();
        }
    }

    private function seedCompletedPickup(User $customer, User $staff): void
    {
        $order = $this->createPricedOrder($customer, 'pickup', [
            [$this->variant('NORDIC-SOFA-GREY'), 1],
            [$this->variant('RUSTIC-TABLE-OAK'), 2],
        ]);
        $order->forceFill(['status' => OrderStatus::COMPLETED])->save();

        $base = Carbon::now()->subDays(9);

        Payment::create([
            'order_id' => $order->id,
            'payment_reference' => ReferenceGenerator::generate(Payment::REFERENCE_PREFIX, 8),
            'provider' => 'internal',
            'method' => 'manual',
            'status' => PaymentStatus::SUCCEEDED,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'provider_transaction_id' => sprintf('TXN-%08d', $order->id),
            'initiated_at' => $base->copy(),
            'confirmed_at' => $base->copy()->addHours(3),
        ]);

        $this->seedHistory($order, $base, [
            [null, OrderStatus::PENDING_PAYMENT, OrderActorType::SYSTEM, null, 0],
            [OrderStatus::PENDING_PAYMENT, OrderStatus::PAID, OrderActorType::SYSTEM, null, 3],
            [OrderStatus::PAID, OrderStatus::ACCEPTED, OrderActorType::STAFF, $staff, 7],
            [OrderStatus::ACCEPTED, OrderStatus::PROCESSING, OrderActorType::STAFF, $staff, 26],
            [OrderStatus::PROCESSING, OrderStatus::READY_FOR_PICKUP, OrderActorType::STAFF, $staff, 50],
            [OrderStatus::READY_FOR_PICKUP, OrderStatus::COMPLETED, OrderActorType::SYSTEM, null, 74],
        ]);
    }

    private function seedCompletedDelivery(User $customer, User $staff): Order
    {
        $order = $this->createPricedOrder($customer, 'deliveryFinalized', [
            [$this->variant('NORDIC-SOFA-BEIGE'), 1],
        ]);
        $order->forceFill(['status' => OrderStatus::COMPLETED])->save();

        Delivery::create([
            'order_id' => $order->id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $order->delivery_address,
            'delivery_instructions' => 'Call on arrival, gate code 42.',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_reference' => ReferenceGenerator::generate(Payment::REFERENCE_PREFIX, 8),
            'provider' => 'internal',
            'method' => 'manual',
            'status' => PaymentStatus::SUCCEEDED,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'provider_transaction_id' => sprintf('TXN-%08d', $order->id),
            'initiated_at' => Carbon::now()->subDays(6),
            'confirmed_at' => Carbon::now()->subDays(6)->addHours(5),
        ]);
        PaymentWebhookEvent::factory()->forPayment($payment)->processed()->create();

        $this->seedHistory($order, Carbon::now()->subDays(6), [
            [null, OrderStatus::PENDING_PAYMENT, OrderActorType::SYSTEM, null, 0],
            [OrderStatus::PENDING_PAYMENT, OrderStatus::PAID, OrderActorType::SYSTEM, null, 5],
            [OrderStatus::PAID, OrderStatus::ACCEPTED, OrderActorType::STAFF, $staff, 9],
            [OrderStatus::ACCEPTED, OrderStatus::PROCESSING, OrderActorType::STAFF, $staff, 30],
            [OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderActorType::STAFF, $staff, 54],
            [OrderStatus::SHIPPED, OrderStatus::DELIVERED, OrderActorType::SYSTEM, null, 78],
            [OrderStatus::DELIVERED, OrderStatus::COMPLETED, OrderActorType::SYSTEM, null, 100],
        ]);

        $order->refresh();

        return $order;
    }

    private function seedPendingOrder(User $customer): Order
    {
        $order = $this->createPricedOrder($customer, 'deliveryPending', [
            [$this->variant('RUSTIC-TABLE-OAK'), 1],
        ]);

        $this->seedHistory($order, Carbon::now()->subHours(5), [
            [null, OrderStatus::PENDING_PAYMENT, OrderActorType::SYSTEM, null, 0],
        ]);

        $order->refresh();

        return $order;
    }

    private function seedCancelledOrder(User $customer): void
    {
        $order = $this->createPricedOrder($customer, 'pickup', [
            [$this->variant('NORDIC-SOFA-GREY'), 1],
        ]);
        $order->forceFill(['status' => OrderStatus::CANCELLED])->save();

        $this->seedHistory($order, Carbon::now()->subDays(2), [
            [null, OrderStatus::PENDING_PAYMENT, OrderActorType::SYSTEM, null, 0],
            [OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED, OrderActorType::CUSTOMER, $customer, 1],
        ]);
    }

    private function seedRequests(User $customer): void
    {
        $sofa = Product::where('slug', 'nordic-3-seater-sofa')->firstOrFail();

        FurnitureRequest::factory()->guest()->frozenCompliant()->create();
        FurnitureRequest::factory()->byUser($customer)->inReview()->forProduct($sofa)->create();
        FurnitureRequest::factory()->closed()->withAllSpecs()->create();
    }

    private function seedEnquiries(User $customer, Order $pending): void
    {
        $sofa = Product::where('slug', 'nordic-3-seater-sofa')->firstOrFail();

        Enquiry::factory()->guest()->create(['category' => EnquiryCategory::GENERAL]);
        Enquiry::factory()->byUser($customer)->forProduct($sofa)->create(['category' => EnquiryCategory::PRODUCT]);
        Enquiry::factory()->byUser($customer)->forOrder($pending)->create(['category' => EnquiryCategory::DELIVERY]);
        Enquiry::factory()->byUser($customer)->closed()->withStaffNote()->create(['category' => EnquiryCategory::OTHER]);
    }

    private function seedNotifications(User $customer, User $staff, Order $delivery): void
    {
        Notification::factory()->forRecipient($customer)
            ->orderNotification(NotificationType::ORDER_SHIPPED)
            ->withTarget('ORDER', (string) $delivery->id)
            ->unread()
            ->create();

        Notification::factory()->forRecipient($staff)
            ->operationalNotification(NotificationType::NEW_ORDER)
            ->read()
            ->create();

        Notification::factory()->forRecipient($staff)
            ->operationalNotification(NotificationType::NEW_ENQUIRY)
            ->unread()
            ->create();
    }
}
