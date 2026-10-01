<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Enquiries\CreateEnquiry;
use App\Services\Enquiries\CreateEnquiryCommand;
use App\Support\EnquiryCategory;
use App\Support\EnquiryStatus;
use App\Support\OrderIdentifier;
use App\Support\ProductIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_enquiry_persists_null_owner_open_status_and_reference(): void
    {
        $enquiry = $this->create();

        $this->assertNull($enquiry->user_id);
        $this->assertSame(EnquiryStatus::OPEN, $enquiry->enquiry_status);
        $this->assertMatchesRegularExpression('/^ENQ-[A-Z0-9]{10}$/', $enquiry->enquiry_reference);
    }

    public function test_authenticated_customer_enquiry_records_owner_and_derives_contact(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Profile Name',
            'email' => 'profile@example.com',
            'phone' => '+255700000700',
        ]);

        $enquiry = $this->create(actor: $customer, name: null, phone: null, email: null);

        $this->assertSame($customer->id, $enquiry->user_id);
        $this->assertSame('Profile Name', $enquiry->name);
        $this->assertSame('profile@example.com', $enquiry->email);
        $this->assertSame('+255700000700', $enquiry->phone);
    }

    public function test_contact_snapshot_is_independent_of_later_profile_changes(): void
    {
        $customer = User::factory()->customer()->create();

        $enquiry = $this->create(actor: $customer, name: 'Snapshot Name', phone: '+255700000111', email: 'snapshot@example.com');

        $customer->update(['name' => 'Changed', 'phone' => '+255700000999', 'email' => 'changed@example.com']);

        $fresh = $enquiry->fresh();
        $this->assertSame('Snapshot Name', $fresh->name);
        $this->assertSame('+255700000111', $fresh->phone);
        $this->assertSame('snapshot@example.com', $fresh->email);
    }

    public function test_product_association_accepts_any_public_product_type(): void
    {
        $inStock = Product::factory()->inStock()->create();
        $madeToOrder = Product::factory()->madeToOrder()->create();

        $this->assertSame($inStock->id, $this->create(productId: ProductIdentifier::encode($inStock))->product_id);
        $this->assertSame($madeToOrder->id, $this->create(productId: ProductIdentifier::encode($madeToOrder))->product_id);
    }

    public function test_owned_order_association_is_persisted(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $enquiry = $this->create(actor: $customer, orderId: OrderIdentifier::encode($order));

        $this->assertSame($order->id, $enquiry->order_id);
    }

    public function test_category_is_persisted(): void
    {
        $enquiry = $this->create(category: EnquiryCategory::DELIVERY);

        $this->assertSame(EnquiryCategory::DELIVERY, $enquiry->fresh()->category);
    }

    public function test_creation_has_no_commerce_or_request_side_effects(): void
    {
        $product = Product::factory()->inStock()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $stock = ProductStock::factory()->forVariant($variant)->create(['quantity' => 4, 'reserved_quantity' => 1]);

        $this->create();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, FurnitureRequest::query()->count());

        $this->assertSame(4, $stock->fresh()->quantity);
        $this->assertSame(1, $stock->fresh()->reserved_quantity);
    }

    public function test_duplicate_submissions_are_not_deduplicated(): void
    {
        $this->create();
        $this->create();

        $this->assertSame(2, Enquiry::query()->count());
    }

    private function create(
        ?User $actor = null,
        ?string $name = 'Asha Mwangi',
        ?string $phone = '+255700000001',
        ?string $email = null,
        string $subject = 'Delivery question',
        string $message = 'Do you deliver furniture to Dodoma?',
        ?EnquiryCategory $category = null,
        ?string $productId = null,
        ?string $orderId = null,
    ): Enquiry {
        return app(CreateEnquiry::class)->create(new CreateEnquiryCommand(
            actor: $actor,
            name: $name,
            phone: $phone,
            email: $email,
            subject: $subject,
            message: $message,
            category: $category,
            productId: $productId,
            orderId: $orderId,
        ));
    }
}
