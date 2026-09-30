<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\FurnitureRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Requests\CreateFurnitureRequest;
use App\Services\Requests\CreateFurnitureRequestCommand;
use App\Support\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureRequestCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_creation_persists_null_owner_and_submitted_status(): void
    {
        $request = $this->create();

        $this->assertNull($request->user_id);
        $this->assertSame(RequestStatus::SUBMITTED, $request->request_status);
        $this->assertDatabaseCount('furniture_requests', 1);
    }

    public function test_authenticated_customer_creation_derives_owner_server_side(): void
    {
        $customer = User::factory()->customer()->create();

        $request = $this->create(actor: $customer);

        $this->assertSame($customer->id, $request->user_id);
    }

    public function test_reference_is_generated_centrally_with_the_frozen_format(): void
    {
        $request = $this->create();

        $this->assertMatchesRegularExpression('/^REQ-[A-Z0-9]{10}$/', $request->request_reference);
    }

    public function test_references_are_unique_across_creations(): void
    {
        $first = $this->create();
        $second = $this->create();

        $this->assertNotSame($first->request_reference, $second->request_reference);
    }

    public function test_public_notes_map_to_the_database_message_column(): void
    {
        $request = $this->create(notes: 'Oak finish please');

        $this->assertSame('Oak finish please', $request->message);
    }

    public function test_omitted_quantity_is_not_defaulted_to_one(): void
    {
        $request = $this->create(quantity: null);

        $this->assertNull($request->quantity);
    }

    public function test_custom_request_persists_without_a_linked_product(): void
    {
        $request = $this->create(productId: null);

        $this->assertNull($request->product_id);
    }

    public function test_schema_only_fields_are_never_populated_by_creation(): void
    {
        $request = $this->create();

        $this->assertNull($request->style);
        $this->assertNull($request->product_details);
        $this->assertNull($request->staff_internal_notes);
    }

    public function test_contact_snapshot_is_independent_of_later_profile_changes(): void
    {
        $customer = User::factory()->customer()->create();

        $request = $this->create(
            actor: $customer,
            name: 'Snapshot Name',
            phone: '+255700000009',
            email: 'snapshot@example.com',
        );

        $customer->update(['name' => 'Changed', 'phone' => '+255700000099', 'email' => 'changed@example.com']);

        $fresh = $request->fresh();
        $this->assertSame('Snapshot Name', $fresh->name);
        $this->assertSame('+255700000009', $fresh->phone);
        $this->assertSame('snapshot@example.com', $fresh->email);
    }

    public function test_creation_has_no_commerce_side_effects(): void
    {
        $product = Product::factory()->madeToOrder()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $stock = ProductStock::factory()->forVariant($variant)->create(['quantity' => 5, 'reserved_quantity' => 1]);

        $this->create(productId: $product->id);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, Cart::query()->count());

        $fresh = $stock->fresh();
        $this->assertSame(5, $fresh->quantity);
        $this->assertSame(1, $fresh->reserved_quantity);
    }

    public function test_duplicate_contact_and_notes_are_not_deduplicated(): void
    {
        $this->create();
        $this->create();

        $this->assertDatabaseCount('furniture_requests', 2);
    }

    private function create(
        ?User $actor = null,
        ?int $productId = null,
        ?int $quantity = null,
        string $name = 'Asha Mwangi',
        ?string $phone = '+255700000001',
        ?string $email = null,
        ?array $dimensions = null,
        ?string $material = null,
        ?string $color = null,
        ?string $notes = 'Custom bookshelf request',
    ): FurnitureRequest {
        return app(CreateFurnitureRequest::class)->create(new CreateFurnitureRequestCommand(
            actor: $actor,
            productId: $productId,
            quantity: $quantity,
            name: $name,
            phone: $phone,
            email: $email,
            dimensions: $dimensions,
            material: $material,
            color: $color,
            notes: $notes,
        ));
    }
}
