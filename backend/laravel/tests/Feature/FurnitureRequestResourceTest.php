<?php

namespace Tests\Feature;

use App\Http\Resources\FurnitureRequestResource;
use App\Models\FurnitureRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductIdentifier;
use App\Support\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_representation_exposes_exactly_the_frozen_customer_fields(): void
    {
        $request = FurnitureRequest::factory()->frozenCompliant()->create([
            'name' => 'Asha Mwangi',
            'phone' => '+255700000001',
            'email' => null,
            'message' => 'Custom bookshelf request',
            'request_status' => RequestStatus::SUBMITTED,
        ]);

        $data = (new FurnitureRequestResource($request))->resolve();

        $this->assertSame([
            'id',
            'product_id',
            'product',
            'quantity',
            'name',
            'phone',
            'email',
            'dimensions',
            'material',
            'color',
            'notes',
            'request_status',
            'attachments',
            'created_at',
            'updated_at',
        ], array_keys($data));

        $this->assertStringStartsWith('req_', $data['id']);
        $this->assertNull($data['product_id']);
        $this->assertNull($data['product']);
        $this->assertNull($data['quantity']);
        $this->assertSame('Asha Mwangi', $data['name']);
        $this->assertSame('+255700000001', $data['phone']);
        $this->assertNull($data['email']);
        $this->assertSame('Custom bookshelf request', $data['notes']);
        $this->assertSame('SUBMITTED', $data['request_status']);
        $this->assertSame([], $data['attachments']);
    }

    public function test_internal_and_schema_only_fields_are_absent(): void
    {
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->madeToOrder()->create();

        $request = FurnitureRequest::factory()->byUser($customer)->forProduct($product)->create([
            'style' => 'Modern',
            'product_details' => ['product_name' => 'Sofa', 'description' => 'Desc'],
            'staff_internal_notes' => 'Operational note',
        ]);

        $data = (new FurnitureRequestResource($request))->resolve();

        foreach (['message', 'style', 'product_details', 'staff_internal_notes', 'user_id', 'request_reference'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
    }

    public function test_notes_are_sourced_from_message_without_exposing_message(): void
    {
        $request = FurnitureRequest::factory()->frozenCompliant()->create(['message' => 'Public notes text']);

        $data = (new FurnitureRequestResource($request))->resolve();

        $this->assertSame('Public notes text', $data['notes']);
        $this->assertArrayNotHasKey('message', $data);
    }

    public function test_linked_product_is_projected_as_a_safe_summary(): void
    {
        $product = Product::factory()->madeToOrder()->create(['name' => 'Oak Sofa', 'slug' => 'oak-sofa']);
        $request = FurnitureRequest::factory()->forProduct($product)->create();

        $data = (new FurnitureRequestResource($request))->resolve();

        $this->assertSame(ProductIdentifier::encode($product), $data['product_id']);
        $this->assertSame([
            'id' => ProductIdentifier::encode($product),
            'name' => 'Oak Sofa',
            'slug' => 'oak-sofa',
        ], $data['product']);
    }

    public function test_partial_dimensions_are_expanded_with_null_members(): void
    {
        $request = FurnitureRequest::factory()->create([
            'dimensions' => ['unit' => 'cm', 'length' => 220],
        ]);

        $data = (new FurnitureRequestResource($request))->resolve();

        $this->assertSame([
            'length' => 220,
            'width' => null,
            'height' => null,
            'unit' => 'cm',
        ], $data['dimensions']);
    }

    public function test_absent_dimensions_remain_null(): void
    {
        $request = FurnitureRequest::factory()->create(['dimensions' => null]);

        $data = (new FurnitureRequestResource($request))->resolve();

        $this->assertNull($data['dimensions']);
    }
}
