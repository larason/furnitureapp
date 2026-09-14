<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\CustomerProfile;
use App\Models\Delivery;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use App\Support\RoleName;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_factories_create_valid_identity_rows(): void
    {
        $customer = User::factory()->customer()->create();
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($customer->hasRole(RoleName::CUSTOMER->value));
        $this->assertTrue($staff->hasRole(RoleName::STAFF->value));
        $this->assertTrue($admin->hasRole(RoleName::ADMIN->value));
        $this->assertNotNull(CustomerProfile::where('user_id', $customer->id)->first());
        $this->assertNotNull(StaffProfile::where('user_id', $staff->id)->first());
        $this->assertNotNull(StaffProfile::where('user_id', $admin->id)->first());
    }

    public function test_profile_factories_create_valid_rows(): void
    {
        $customerProfile = CustomerProfile::factory()->create();
        $staffProfile = StaffProfile::factory()->create();

        $this->assertNotNull(User::find($customerProfile->user_id));
        $this->assertNotNull(User::find($staffProfile->user_id));
    }

    public function test_category_factory_creates_valid_hierarchy(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->for($parent, 'parent')->create();

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_product_catalog_factories_create_coherent_graph(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->asDefault()->create();
        $image = ProductImage::factory()->for($product)->asPrimary()->create();
        $stock = ProductStock::factory()->forVariant($variant)->create();

        $this->assertTrue($product->variants->contains($variant));
        $this->assertTrue($product->images->contains($image));
        $this->assertTrue($product->defaultVariant->is($variant));
        $this->assertTrue($product->primaryImage->is($image));
        $this->assertTrue($variant->stocks->contains($stock));
        $this->assertGreaterThanOrEqual(0, $stock->reserved_quantity);
        $this->assertLessThanOrEqual($stock->quantity, $stock->reserved_quantity);
    }

    public function test_out_of_stock_state_is_valid(): void
    {
        $stock = ProductStock::factory()->outOfStock()->create();

        $this->assertSame(0, $stock->quantity);
        $this->assertSame(0, $stock->reserved_quantity);
        $this->assertSame(0, $stock->available_quantity);
    }

    public function test_cart_factories_cover_guest_and_customer_paths(): void
    {
        $customer = User::factory()->create();
        $customerCart = Cart::factory()->for($customer)->active()->create();
        $historic = Cart::factory()->for($customer)->inactive()->create();
        $guest = Cart::factory()->guestOwned()->create();

        $this->assertTrue($customerCart->isCustomerOwned());
        $this->assertTrue($historic->isCustomerOwned());
        $this->assertFalse($historic->isActive());
        $this->assertTrue($guest->isGuest());
        $this->assertNull($guest->user_id);
    }

    public function test_order_factory_totals_are_coherent(): void
    {
        $pickup = Order::factory()->pickup()->create();
        $pending = Order::factory()->deliveryPending()->create();
        $finalized = Order::factory()->deliveryFinalized(15000)->create();

        $this->assertSame($pickup->subtotal_amount, $pickup->total_amount);
        $this->assertSame(0, $pickup->delivery_fee_amount);
        $this->assertNull($pending->delivery_fee_amount);
        $this->assertSame($pending->subtotal_amount, $pending->total_amount);
        $this->assertSame(15000, $finalized->delivery_fee_amount);
        $this->assertSame($finalized->subtotal_amount + 15000, $finalized->total_amount);
    }

    public function test_status_history_factory_builds_coherent_timeline(): void
    {
        $order = Order::factory()->create();
        $initial = OrderStatusHistory::factory()->for($order)->initial()->create();
        $next = OrderStatusHistory::factory()->for($order)
            ->transition(OrderStatus::PENDING_PAYMENT, OrderStatus::PAID)
            ->create();

        $timeline = $order->fresh()->statusHistory;

        $this->assertNull($initial->from_status);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $initial->to_status);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $next->from_status);
        $this->assertEquals(
            [OrderStatus::PENDING_PAYMENT, OrderStatus::PAID],
            $timeline->pluck('to_status')->all()
        );
    }

    public function test_delivery_and_payment_factories_match_orders(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->create();
        $delivery = Delivery::factory()->forOrder($order)->create();
        $payment = Payment::factory()->for($order)->succeeded()->create(['amount' => $order->total_amount]);

        $this->assertTrue($order->fresh()->delivery->is($delivery));
        $this->assertSame($order->total_amount, $payment->amount);
        $this->assertSame($order->currency, $payment->currency);
    }

    public function test_request_enquiry_notification_factories_are_valid(): void
    {
        $user = User::factory()->create();
        $request = FurnitureRequest::factory()->byUser($user)->create();
        $enquiry = Enquiry::factory()->byUser($user)->create();
        $notification = Notification::factory()->forRecipient($user)->unread()->create();

        $this->assertTrue($request->user->is($user));
        $this->assertTrue($enquiry->user->is($user));
        $this->assertTrue($notification->recipient->is($user));
        $this->assertNull($notification->read_at);
    }

    public function test_factory_sequences_respect_unique_constraints(): void
    {
        $products = Product::factory()->count(3)->create();
        $variants = ProductVariant::factory()->count(3)->create();

        $this->assertCount(3, $products->pluck('slug')->unique());
        $this->assertCount(3, $variants->pluck('sku')->unique());
        $this->assertCount(3, OrderItem::factory()->count(3)->create()->pluck('id')->unique());
    }

    public function test_reference_seeders_are_idempotent(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(CategorySeeder::class);

        $roleCount = Role::count();
        $categoryCount = Category::count();

        $this->seed(RbacSeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(3, $roleCount);
        $this->assertSame($categoryCount, Category::count());
        $this->assertGreaterThan(0, $categoryCount);
    }

    public function test_default_database_seeder_creates_no_demo_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
        $this->assertGreaterThan(0, Category::count());
    }

    public function test_demo_seeder_builds_coherent_graph(): void
    {
        $this->seed(DemoSeeder::class);

        $customer = User::where('email', 'customer@example.com')->firstOrFail();

        $this->assertSame(1, Cart::where('user_id', $customer->id)->where('status', 'ACTIVE')->count());
        $this->assertGreaterThanOrEqual(1, $customer->orders()->count());

        foreach ($customer->orders as $order) {
            $this->assertGreaterThanOrEqual(1, $order->items->count());
            $this->assertGreaterThanOrEqual(1, $order->statusHistory->count());
            $this->assertSame($order->status, $order->statusHistory->sortBy('id')->last()->to_status);

            foreach ($order->items as $item) {
                $this->assertSame($item->unit_price_amount * $item->quantity, $item->line_total_amount);
            }
        }

        $this->assertSame(1, Delivery::count());
        $this->assertTrue(
            Delivery::first()->order->fulfillment_type === FulfillmentType::DELIVERY
        );
        $this->assertGreaterThanOrEqual(1, FurnitureRequest::count());
        $this->assertGreaterThanOrEqual(1, Enquiry::count());
        $this->assertGreaterThanOrEqual(1, Notification::count());
    }

    public function test_demo_seeder_skips_when_demo_orders_exist(): void
    {
        $this->seed(DemoSeeder::class);

        $orderCount = Order::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($orderCount, Order::count());
    }
}
