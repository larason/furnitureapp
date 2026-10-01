<?php

namespace Tests\Feature;

use App\Http\Resources\EnquiryResource;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderIdentifier;
use App\Support\ProductIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_representation_exposes_exactly_the_frozen_fields(): void
    {
        $enquiry = Enquiry::factory()->general()->create([
            'name' => 'Asha',
            'email' => 'asha@example.com',
            'phone' => null,
            'subject' => 'Delivery question',
            'message' => 'Do you deliver to Dodoma?',
            'category' => null,
        ]);

        $data = (new EnquiryResource($enquiry))->resolve();

        $this->assertSame([
            'id',
            'name',
            'email',
            'phone',
            'subject',
            'message',
            'category',
            'product_id',
            'product',
            'order_id',
            'order',
            'enquiry_status',
            'attachments',
            'created_at',
            'updated_at',
        ], array_keys($data));

        $this->assertStringStartsWith('enq_', $data['id']);
        $this->assertSame('OPEN', $data['enquiry_status']);
        $this->assertNull($data['category']);
        $this->assertSame([], $data['attachments']);
    }

    public function test_internal_fields_are_absent(): void
    {
        $enquiry = Enquiry::factory()->withStaffNote()->create();

        $data = (new EnquiryResource($enquiry))->resolve();

        foreach (['user_id', 'staff_internal_notes', 'enquiry_reference'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
    }

    public function test_linked_product_is_projected_as_a_safe_summary(): void
    {
        $product = Product::factory()->inStock()->create(['name' => 'Ready Sofa', 'slug' => 'ready-sofa']);
        $enquiry = Enquiry::factory()->forProduct($product)->create();

        $enquiry->load('product');
        $data = (new EnquiryResource($enquiry))->resolve();

        $this->assertSame(ProductIdentifier::encode($product), $data['product_id']);
        $this->assertSame([
            'id' => ProductIdentifier::encode($product),
            'name' => 'Ready Sofa',
            'slug' => 'ready-sofa',
        ], $data['product']);
    }

    public function test_linked_order_is_projected_as_a_safe_summary(): void
    {
        $order = Order::factory()->create();
        $enquiry = Enquiry::factory()->forOrder($order)->create();

        $enquiry->load('order');
        $data = (new EnquiryResource($enquiry))->resolve();

        $this->assertSame(OrderIdentifier::encode($order), $data['order_id']);
        $this->assertSame([
            'id' => OrderIdentifier::encode($order),
            'order_reference' => $order->order_reference,
            'status' => 'PENDING_PAYMENT',
        ], $data['order']);
    }

    public function test_absent_associations_are_null(): void
    {
        $enquiry = Enquiry::factory()->general()->create();

        $data = (new EnquiryResource($enquiry))->resolve();

        $this->assertNull($data['product_id']);
        $this->assertNull($data['product']);
        $this->assertNull($data['order_id']);
        $this->assertNull($data['order']);
    }

    public function test_category_is_serialized_as_its_enum_value(): void
    {
        $enquiry = Enquiry::factory()->create(['category' => 'DELIVERY']);

        $data = (new EnquiryResource($enquiry))->resolve();

        $this->assertSame('DELIVERY', $data['category']);
    }

    public function test_authenticated_enquiry_does_not_expose_user_id(): void
    {
        $customer = User::factory()->customer()->create();
        $enquiry = Enquiry::factory()->byUser($customer)->create();

        $data = (new EnquiryResource($enquiry))->resolve();

        $this->assertArrayNotHasKey('user_id', $data);
    }
}
