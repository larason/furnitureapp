<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
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
use App\Models\ProductImage;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use App\Support\SpaceType;
use Database\Factories\OrderFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(string $slug, ?Category $parent = null): Category
    {
        $category = new Category([
            'name' => 'Category '.$slug,
            'slug' => $slug,
            'space_type' => SpaceType::HOME->value,
            'display_order' => 0,
        ]);
        $category->parent_id = $parent?->id;
        $category->save();

        return $category;
    }

    public function test_order_item_with_unknown_order_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('order_items')->insert([
            'order_id' => 999999,
            'product_id' => null,
            'variant_id' => null,
            'sku' => 'SKU-X',
            'name' => 'Ghost item',
            'unit_price_amount' => 1000,
            'quantity' => 1,
            'line_total_amount' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cart_item_with_unknown_cart_is_rejected(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('cart_items')->insert([
            'cart_id' => 999999,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_payment_with_unknown_order_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('payments')->insert([
            'order_id' => 999999,
            'payment_reference' => 'PAY-'.strtoupper(fake()->bothify('????????')),
            'provider' => 'test-provider',
            'method' => 'card',
            'status' => 'PENDING',
            'amount' => 1000,
            'currency' => 'TZS',
            'initiated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleting_parent_category_detaches_children(): void
    {
        $parent = $this->createCategory('parent-cat');
        $child = $this->createCategory('child-cat', $parent);

        $parent->delete();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_deleting_category_with_products_is_blocked(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        $product->category->delete();
    }

    public function test_deleting_product_cascades_variants_and_images(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $image = ProductImage::factory()->for($product)->create();
        $variantId = $variant->id;
        $imageId = $image->id;

        $product->forceDelete();

        $this->assertNull(ProductVariant::find($variantId));
        $this->assertNull(ProductImage::find($imageId));
    }

    public function test_deleting_variant_cascades_stock_rows(): void
    {
        $variant = ProductVariant::factory()->create();
        $stock = ProductStock::factory()->forVariant($variant)->create();
        $stockId = $stock->id;

        $variant->delete();

        $this->assertNull(ProductStock::find($stockId));
    }

    public function test_deleting_cart_cascades_items(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $item = CartItem::factory()->for($cart)->create();
        $itemId = $item->id;

        $cart->delete();

        $this->assertNull(CartItem::find($itemId));
    }

    public function test_deleting_order_cascades_items_and_history(): void
    {
        $order = Order::factory()->create();
        $item = OrderItem::factory()->for($order)->create();
        $event = OrderStatusHistory::factory()->for($order)->create();
        $itemId = $item->id;
        $eventId = $event->id;

        $order->delete();

        $this->assertNull(OrderItem::find($itemId));
        $this->assertNull(OrderStatusHistory::find($eventId));
    }

    public function test_deleting_customer_with_orders_is_blocked(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user, 'customer')->create();

        $this->expectException(QueryException::class);

        $user->delete();
    }

    public function test_archiving_product_with_cart_items_retains_the_item(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = Product::factory()->create();
        $item = CartItem::factory()->for($cart)->for($product)->create();

        $product->delete();

        $this->assertNotNull($item->fresh());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_hard_deleting_product_with_cart_items_is_blocked(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = Product::factory()->create();
        CartItem::factory()->for($cart)->for($product)->create();

        $this->expectException(QueryException::class);

        $product->forceDelete();
    }

    public function test_deleting_variant_with_cart_items_is_blocked(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        CartItem::factory()->for($cart)->for($product)->for($variant, 'variant')->create();

        $this->expectException(QueryException::class);

        $variant->delete();
    }

    public function test_deleting_order_with_payments_is_blocked(): void
    {
        $order = Order::factory()->create();
        Payment::factory()->for($order)->create();

        $this->expectException(QueryException::class);

        $order->delete();
    }

    public function test_deleting_order_with_delivery_is_blocked(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        Delivery::factory()->forOrder($order)->create();

        $this->expectException(QueryException::class);

        $order->delete();
    }

    public function test_deleting_order_with_enquiries_is_blocked(): void
    {
        $order = Order::factory()->create();
        Enquiry::factory()->forOrder($order)->create();

        $this->expectException(QueryException::class);

        $order->delete();
    }

    public function test_deleting_user_preserves_carts(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->create();
        $cartId = $cart->id;

        try {
            $user->delete();
            $this->fail('Deleting a customer with carts must not succeed silently.');
        } catch (QueryException) {
            $this->assertNotNull(Cart::find($cartId));
        }
    }

    public function test_deleting_user_retains_furniture_request(): void
    {
        $user = User::factory()->create();
        $request = FurnitureRequest::factory()->byUser($user)->create();
        $reference = $request->request_reference;

        $user->delete();

        $retained = FurnitureRequest::where('request_reference', $reference)->firstOrFail();
        $this->assertNull($retained->user_id);
        $this->assertSame($request->name, $retained->name);
    }

    public function test_deleting_product_retains_furniture_request(): void
    {
        $product = Product::factory()->create();
        $request = FurnitureRequest::factory()->forProduct($product)->create();
        $reference = $request->request_reference;

        $product->forceDelete();

        $retained = FurnitureRequest::where('request_reference', $reference)->firstOrFail();
        $this->assertNull($retained->product_id);
    }

    public function test_deleting_user_retains_enquiry(): void
    {
        $user = User::factory()->create();
        $enquiry = Enquiry::factory()->byUser($user)->create();
        $reference = $enquiry->enquiry_reference;

        $user->delete();

        $retained = Enquiry::where('enquiry_reference', $reference)->firstOrFail();
        $this->assertNull($retained->user_id);
        $this->assertSame($enquiry->message, $retained->message);
    }

    public function test_deleting_product_retains_enquiry(): void
    {
        $product = Product::factory()->create();
        $enquiry = Enquiry::factory()->forProduct($product)->create();

        $product->forceDelete();

        $this->assertNull($enquiry->fresh()->product_id);
    }

    public function test_deleting_payment_retains_webhook_events(): void
    {
        $payment = Payment::factory()->create();
        $event = PaymentWebhookEvent::factory()->forPayment($payment)->create();
        $eventId = $event->id;

        $payment->delete();

        $retained = PaymentWebhookEvent::find($eventId);
        $this->assertNotNull($retained);
        $this->assertNull($retained->payment_id);
    }

    public function test_deleting_user_with_notifications_is_blocked(): void
    {
        $user = User::factory()->create();
        Notification::factory()->forRecipient($user)->create();

        $this->expectException(QueryException::class);

        $user->delete();
    }

    public function test_duplicate_payment_reference_is_rejected(): void
    {
        $payment = Payment::factory()->create();
        $row = (array) DB::table('payments')->where('id', $payment->id)->first();
        unset($row['id']);

        $this->expectException(QueryException::class);

        DB::table('payments')->insert($row);
    }

    public function test_duplicate_webhook_provider_event_is_rejected(): void
    {
        $event = PaymentWebhookEvent::factory()->received()->create();
        $row = (array) DB::table('payment_webhook_events')->where('id', $event->id)->first();
        unset($row['id']);

        $this->expectException(QueryException::class);

        DB::table('payment_webhook_events')->insert($row);
    }

    public function test_duplicate_delivery_for_one_order_is_rejected(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        $delivery = Delivery::factory()->forOrder($order)->create();
        $row = (array) DB::table('deliveries')->where('id', $delivery->id)->first();
        unset($row['id']);

        $this->expectException(QueryException::class);

        DB::table('deliveries')->insert($row);
    }

    public function test_duplicate_guest_token_digest_is_rejected(): void
    {
        $cart = Cart::factory()->guestOwned()->create();

        $this->expectException(QueryException::class);

        DB::table('carts')->insert([
            'user_id' => null,
            'guest_token_digest' => $cart->guest_token_digest,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_second_active_cart_for_customer_is_rejected(): void
    {
        $user = User::factory()->create();
        Cart::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        DB::table('carts')->insert([
            'user_id' => $user->id,
            'guest_token_digest' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cart_with_both_owners_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('carts')->insert([
            'user_id' => $user->id,
            'guest_token_digest' => str_repeat('a', 64),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cart_with_no_owner_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('carts')->insert([
            'user_id' => null,
            'guest_token_digest' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cart_with_unknown_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('carts')->insert([
            'user_id' => null,
            'guest_token_digest' => str_repeat('b', 64),
            'status' => 'ABANDONED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cart_item_quantity_bounds_are_rejected(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = Product::factory()->create();

        foreach ([0, 101] as $quantity) {
            try {
                DB::table('cart_items')->insert([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->fail("Cart item quantity {$quantity} must be rejected.");
            } catch (QueryException) {
            }
        }

        $this->assertSame(0, $cart->items()->count());
    }

    public function test_furniture_request_quantity_bounds_are_rejected(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('furniture_requests')->where('id', $request->id)->update(['quantity' => 0]);
    }

    public function test_negative_display_order_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('categories')->insert([
            'name' => 'Negative Order',
            'slug' => 'negative-order-cat',
            'space_type' => SpaceType::HOME->value,
            'display_order' => -1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_self_recommendation_is_rejected(): void
    {
        $category = $this->createCategory('self-rec-cat');

        $this->expectException(QueryException::class);

        DB::table('category_recommendations')->insert([
            'category_id' => $category->id,
            'recommended_category_id' => $category->id,
            'relation_type' => 'COMPLEMENTARY',
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_blank_warehouse_location_is_rejected(): void
    {
        $stock = ProductStock::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('product_stocks')->where('id', $stock->id)->update(['warehouse_location' => '   ']);
    }

    public function test_pickup_order_with_pending_fee_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'customer_id' => User::factory()->create()->id,
            'order_reference' => OrderFactory::generateReference(),
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'fulfillment_type' => FulfillmentType::PICKUP->value,
            'delivery_fee_status' => DeliveryFeeStatus::PENDING->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => 10000,
            'delivery_fee_amount' => null,
            'total_amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_finalized_delivery_with_wrong_total_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'customer_id' => User::factory()->create()->id,
            'order_reference' => OrderFactory::generateReference(),
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'fulfillment_type' => FulfillmentType::DELIVERY->value,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => 10000,
            'delivery_fee_amount' => 2000,
            'total_amount' => 10000,
            'delivery_address' => json_encode(['address_line' => '1 Main St', 'city' => 'Dar es Salaam', 'region' => 'Dar es Salaam']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleting_product_retains_order_snapshots(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();
        $item = OrderItem::factory()->for($order)->for($product)->create();
        $itemId = $item->id;
        $sku = $item->sku;
        $name = $item->name;

        $product->forceDelete();

        $retained = OrderItem::find($itemId);
        $this->assertNotNull($retained);
        $this->assertNull($retained->product_id);
        $this->assertSame($sku, $retained->sku);
        $this->assertSame($name, $retained->name);
    }

    public function test_deleting_variant_retains_order_snapshots(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $order = Order::factory()->create();
        $item = OrderItem::factory()->for($order)->forVariant($variant, $product)->create();
        $itemId = $item->id;

        $variant->delete();

        $retained = OrderItem::find($itemId);
        $this->assertNotNull($retained);
        $this->assertNull($retained->variant_id);
        $this->assertSame($item->line_total_amount, $retained->line_total_amount);
    }

    public function test_soft_deleted_product_is_hidden_but_recoverable(): void
    {
        $product = Product::factory()->create();
        $productId = $product->id;

        $product->delete();

        $this->assertNull(Product::find($productId));
        $this->assertNotNull(Product::withTrashed()->find($productId));
    }
}
