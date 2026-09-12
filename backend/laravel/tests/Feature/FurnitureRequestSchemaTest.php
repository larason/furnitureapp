<?php

namespace Tests\Feature;

use App\Models\FurnitureRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\RequestStatus;
use Database\Factories\FurnitureRequestFactory;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FurnitureRequestSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('furniture_requests'));
        $this->assertTrue(Schema::hasColumns('furniture_requests', [
            'id',
            'user_id',
            'request_reference',
            'product_id',
            'product_details',
            'style',
            'name',
            'email',
            'phone',
            'message',
            'quantity',
            'dimensions',
            'material',
            'color',
            'request_status',
            'staff_internal_notes',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_guest_request_can_exist_with_null_user(): void
    {
        $request = FurnitureRequest::factory()->guest()->create();

        $this->assertNull($request->user_id);
        $this->assertNotEmpty($request->name);
        $this->assertNotEmpty($request->email);
        $this->assertNotEmpty($request->phone);
    }

    public function test_frozen_compliant_request_can_omit_optional_fields(): void
    {
        $request = FurnitureRequest::factory()->frozenCompliant()->create([
            'name' => 'Asha Mwangi',
            'email' => 'asha@example.com',
        ]);

        $this->assertNull($request->fresh()->product_details);
        $this->assertNull($request->fresh()->style);
        $this->assertNull($request->fresh()->message);
    }

    public function test_authenticated_request_stores_user_and_contact_snapshot(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'email' => 'original@example.com']);

        $request = FurnitureRequest::factory()->byUser($user)->create();

        $this->assertSame($user->id, $request->user_id);
        $this->assertTrue($request->user->is($user));
    }

    public function test_request_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $request = FurnitureRequest::factory()->byUser($user)->create();

        $this->assertTrue($request->user->is($user));
    }

    public function test_user_has_many_requests(): void
    {
        $user = User::factory()->create();
        $first = FurnitureRequest::factory()->byUser($user)->create();
        $second = FurnitureRequest::factory()->byUser($user)->create();

        $requests = $user->fresh()->furnitureRequests;

        $this->assertCount(2, $requests);
        $this->assertTrue($requests->contains($first));
        $this->assertTrue($requests->contains($second));
    }

    public function test_request_reference_is_unique_and_formatted(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->assertMatchesRegularExpression('/^REQ-[A-Z0-9]{10}$/', $request->request_reference);
        $this->assertNotSame($request->id, $request->request_reference);
    }

    public function test_duplicate_request_reference_is_rejected(): void
    {
        $existing = FurnitureRequest::factory()->create();

        $this->expectException(QueryException::class);

        FurnitureRequest::factory()->create(['request_reference' => $existing->request_reference]);
    }

    public function test_request_reference_is_immutable(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request reference is immutable.');

        $request->request_reference = FurnitureRequestFactory::generateReference();
        $request->save();
    }

    public function test_custom_request_can_have_null_product(): void
    {
        $request = FurnitureRequest::factory()->create(['product_id' => null]);

        $this->assertNull($request->product_id);
    }

    public function test_request_can_reference_a_product(): void
    {
        $product = Product::factory()->create();
        $request = FurnitureRequest::factory()->forProduct($product)->create();

        $this->assertSame($product->id, $request->product_id);
        $this->assertTrue($request->product->is($product));
    }

    public function test_product_can_have_many_requests(): void
    {
        $product = Product::factory()->create();
        $first = FurnitureRequest::factory()->forProduct($product)->create();
        $second = FurnitureRequest::factory()->forProduct($product)->create();

        $requests = $product->fresh()->furnitureRequests;

        $this->assertCount(2, $requests);
        $this->assertTrue($requests->contains($first));
        $this->assertTrue($requests->contains($second));
    }

    public function test_deleting_product_nulls_product_id_without_deleting_request(): void
    {
        $product = Product::factory()->create();
        $request = FurnitureRequest::factory()->forProduct($product)->create();

        $product->delete();

        $fresh = $request->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->product_id);
    }

    public function test_deleting_user_nulls_user_id_without_deleting_request(): void
    {
        $user = User::factory()->create();
        $request = FurnitureRequest::factory()->byUser($user)->create();

        $user->delete();

        $fresh = $request->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->user_id);
    }

    public function test_contact_fields_persist(): void
    {
        $request = FurnitureRequest::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+255700000000',
        ]);

        $fresh = $request->fresh();

        $this->assertSame('Jane Doe', $fresh->name);
        $this->assertSame('jane@example.com', $fresh->email);
        $this->assertSame('+255700000000', $fresh->phone);
        $this->assertIsString($fresh->phone);
    }

    public function test_request_requires_name(): void
    {
        $request = FurnitureRequest::factory()->make(['name' => '   ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request name is required.');

        $request->save();
    }

    public function test_request_requires_at_least_one_contact_channel(): void
    {
        $request = FurnitureRequest::factory()->make(['email' => null, 'phone' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A request requires at least one of email or phone.');

        $request->save();
    }

    public function test_request_permits_only_email(): void
    {
        $request = FurnitureRequest::factory()->create(['phone' => null]);

        $this->assertNull($request->phone);
        $this->assertNotEmpty($request->email);
    }

    public function test_request_permits_only_phone(): void
    {
        $request = FurnitureRequest::factory()->create(['email' => null]);

        $this->assertNull($request->email);
        $this->assertNotEmpty($request->phone);
    }

    public function test_style_persists(): void
    {
        $request = FurnitureRequest::factory()->create(['style' => 'Modern Minimalist']);

        $this->assertSame('Modern Minimalist', $request->fresh()->style);
    }

    public function test_style_is_optional_and_bounded(): void
    {
        $request = FurnitureRequest::factory()->create(['style' => null]);

        $this->assertNull($request->fresh()->style);
    }

    public function test_blank_style_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make(['style' => '   ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request style is required.');

        $request->save();
    }

    public function test_message_persists(): void
    {
        $request = FurnitureRequest::factory()->create([
            'message' => 'I would like this made in an oak finish.',
        ]);

        $this->assertSame('I would like this made in an oak finish.', $request->fresh()->message);
    }

    public function test_message_is_optional_and_bounded(): void
    {
        $request = FurnitureRequest::factory()->create(['message' => null]);

        $this->assertNull($request->fresh()->message);
    }

    public function test_blank_message_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make(['message' => '   ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request message is required.');

        $request->save();
    }

    public function test_style_at_limit_with_whitespace_is_trimmed_and_stored(): void
    {
        $request = FurnitureRequest::factory()->create(['style' => '  '.str_repeat('a', 200).'  ']);

        $this->assertSame(200, mb_strlen($request->fresh()->style));
    }

    public function test_style_over_limit_with_whitespace_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make(['style' => '  '.str_repeat('a', 201).'  ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request style is too long.');

        $request->save();
    }

    public function test_message_at_limit_with_whitespace_is_trimmed_and_stored(): void
    {
        $request = FurnitureRequest::factory()->create(['message' => '  '.str_repeat('b', 5000).'  ']);

        $this->assertSame(5000, mb_strlen($request->fresh()->message));
    }

    public function test_message_over_limit_with_whitespace_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make(['message' => '  '.str_repeat('b', 5001).'  ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request message is too long.');

        $request->save();
    }

    public function test_valid_product_details_structure_persists(): void
    {
        $details = ['product_name' => 'Modern sofa', 'description' => 'Three-seat with deep cushions'];

        $request = FurnitureRequest::factory()->create(['product_details' => $details]);

        $this->assertSame($details, $request->fresh()->product_details);
    }

    public function test_product_details_with_unknown_fields_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make([
            'product_details' => ['anything' => 'arbitrary', 'secret' => 'x'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product details contain unsupported fields.');

        $request->save();
    }

    public function test_product_details_reject_array_values(): void
    {
        $request = FurnitureRequest::factory()->make([
            'product_details' => ['product_name' => ['secret' => 'x'], 'description' => 'desc'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product details product_name is required.');

        $request->save();
    }

    public function test_product_details_reject_object_values(): void
    {
        $request = FurnitureRequest::factory()->make([
            'product_details' => ['description' => (object) ['x' => 1], 'product_name' => 'Sofa'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product details description is required.');

        $request->save();
    }

    public function test_product_details_reject_null_required_value(): void
    {
        $request = FurnitureRequest::factory()->make([
            'product_details' => ['product_name' => null, 'description' => 'desc'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product details product_name is required.');

        $request->save();
    }

    public function test_product_details_reject_missing_required_value(): void
    {
        $request = FurnitureRequest::factory()->make([
            'product_details' => ['description' => 'desc only'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product details product_name is required.');

        $request->save();
    }

    public function test_product_details_reference_requires_string_or_null(): void
    {
        $request = FurnitureRequest::factory()->make([
            'product_details' => ['product_name' => 'Sofa', 'description' => 'desc', 'reference' => ['x']],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product details reference must be a string or null.');

        $request->save();
    }

    public function test_product_details_blank_value_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make([
            'product_details' => ['product_name' => '   ', 'description' => 'desc'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product details product_name is required.');

        $request->save();
    }

    public function test_product_details_null_reference_is_permitted(): void
    {
        $request = FurnitureRequest::factory()->create([
            'product_details' => ['product_name' => 'Sofa', 'description' => 'desc', 'reference' => null],
        ]);

        $this->assertSame('Sofa', $request->fresh()->product_details['product_name']);
        $this->assertNull($request->fresh()->product_details['reference']);
    }

    public function test_valid_dimensions_structure_persists(): void
    {
        $dimensions = ['length' => 220, 'width' => 90, 'height' => 85, 'unit' => 'cm'];

        $request = FurnitureRequest::factory()->create(['dimensions' => $dimensions]);

        $this->assertSame($dimensions, $request->fresh()->dimensions);
    }

    public function test_decimal_dimensions_are_accepted(): void
    {
        $dimensions = ['length' => 220.5, 'width' => 90.25, 'height' => 85, 'unit' => 'cm'];

        $request = FurnitureRequest::factory()->create(['dimensions' => $dimensions]);

        $this->assertSame($dimensions, $request->fresh()->dimensions);
    }

    public function test_zero_dimension_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make([
            'dimensions' => ['length' => 0, 'width' => 90, 'height' => 85, 'unit' => 'cm'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Dimensions length must be a positive number up to 10000.');

        $request->save();
    }

    public function test_negative_dimension_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make([
            'dimensions' => ['length' => -5, 'width' => 90, 'height' => 85, 'unit' => 'cm'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Dimensions length must be a positive number up to 10000.');

        $request->save();
    }

    public function test_over_max_dimension_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make([
            'dimensions' => ['length' => 10001, 'width' => 90, 'height' => 85, 'unit' => 'cm'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Dimensions length must be a positive number up to 10000.');

        $request->save();
    }

    public function test_dimensions_unknown_keys_are_rejected(): void
    {
        $request = FurnitureRequest::factory()->make([
            'dimensions' => ['length' => 220, 'width' => 90, 'height' => 85, 'unit' => 'cm', 'depth' => 60],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Dimensions contain unsupported fields.');

        $request->save();
    }

    public function test_dimensions_require_cm_unit(): void
    {
        $request = FurnitureRequest::factory()->make([
            'dimensions' => ['length' => 220, 'width' => 90, 'height' => 85, 'unit' => 'in'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Dimensions unit must be cm.');

        $request->save();
    }

    public function test_quantity_is_optional_and_nullable(): void
    {
        $request = FurnitureRequest::factory()->create(['quantity' => null]);

        $this->assertNull($request->quantity);
    }

    public function test_quantity_out_of_range_is_rejected(): void
    {
        $request = FurnitureRequest::factory()->make(['quantity' => 101]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request quantity must be between 1 and 100.');

        $request->save();
    }

    public function test_material_and_color_are_optional(): void
    {
        $request = FurnitureRequest::factory()->create(['material' => null, 'color' => null]);

        $this->assertNull($request->material);
        $this->assertNull($request->color);
    }

    public function test_material_persists(): void
    {
        $request = FurnitureRequest::factory()->withMaterialAndColor()->create();

        $this->assertNotEmpty($request->material);
        $this->assertNotEmpty($request->color);
    }

    public function test_default_status_is_submitted(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->assertSame(RequestStatus::SUBMITTED, $request->request_status);
    }

    public function test_closed_request_status_is_persisted(): void
    {
        $request = FurnitureRequest::factory()->closed()->create();

        $this->assertSame(RequestStatus::CLOSED, $request->fresh()->request_status);
    }

    public function test_arbitrary_request_status_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        FurnitureRequest::factory()->make(['request_status' => 'QUOTED']);
    }

    public function test_specs_facade_states_persist(): void
    {
        $all = FurnitureRequest::factory()->withAllSpecs()->create();

        $this->assertNotNull($all->quantity);
        $this->assertNotNull($all->dimensions);
        $this->assertNotNull($all->material);
        $this->assertNotNull($all->color);
    }

    public function test_intake_fields_are_immutable_after_submission(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request name is immutable once submitted.');

        $request->name = 'Changed';
        $request->save();
    }

    public function test_historical_request_untouched_by_product_and_user_changes(): void
    {
        $user = User::factory()->create(['name' => 'OG', 'email' => 'og@example.com']);
        $product = Product::factory()->create(['name' => 'Original Product']);

        $request = FurnitureRequest::factory()->byUser($user)->forProduct($product)->create([
            'product_details' => ['product_name' => 'Submitted Sofa', 'description' => 'A submitted sofa'],
            'style' => 'Modern',
            'message' => 'Original message',
            'name' => 'Submitter',
            'email' => 'submitter@example.com',
            'phone' => '+255700000001',
        ]);

        $user->update(['name' => 'Changed User', 'email' => 'changed@example.com']);
        $product->update(['name' => 'Renamed Product']);

        $fresh = $request->fresh();

        $this->assertSame('Submitted Sofa', $fresh->product_details['product_name']);
        $this->assertSame('Modern', $fresh->style);
        $this->assertSame('Original message', $fresh->message);
        $this->assertSame('Submitter', $fresh->name);
        $this->assertSame('submitter@example.com', $fresh->email);
        $this->assertSame('+255700000001', $fresh->phone);
    }

    public function test_duplicate_contact_and_message_are_allowed(): void
    {
        $base = [
            'name' => 'Same Person',
            'email' => 'same@example.com',
            'phone' => '+255700000002',
            'message' => 'Identical message text',
        ];

        $first = FurnitureRequest::factory()->create($base);
        $second = FurnitureRequest::factory()->create($base);

        $this->assertNotNull($first->id);
        $this->assertNotNull($second->id);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, FurnitureRequest::count());
    }

    public function test_staff_internal_notes_are_stored_separately(): void
    {
        $request = FurnitureRequest::factory()->create([
            'message' => 'Customer message',
            'staff_internal_notes' => 'Operational note',
        ]);

        $this->assertSame('Customer message', $request->message);
        $this->assertSame('Operational note', $request->staff_internal_notes);
    }

    public function test_no_order_or_payment_columns_exist(): void
    {
        $columns = Schema::getColumnListing('furniture_requests');

        foreach (['order_id', 'payment_id', 'payment_status', 'quoted_price', 'amount', 'currency', 'inventory_id', 'warehouse_location', 'delivery_id', 'delivery_fee', 'delivery_status'] as $col) {
            $this->assertNotContains($col, $columns, "Forbidden column {$col} must not exist");
        }
    }

    public function test_no_guest_type_or_is_guest_columns_exist(): void
    {
        $columns = Schema::getColumnListing('furniture_requests');

        foreach (['guest_user', 'guest_customer', 'customer_type', 'is_guest'] as $col) {
            $this->assertNotContains($col, $columns);
        }
    }

    public function test_request_requires_valid_user_foreign_key_when_set(): void
    {
        $this->expectException(QueryException::class);

        FurnitureRequest::factory()->create(['user_id' => 999999]);
    }

    public function test_request_requires_valid_product_foreign_key_when_set(): void
    {
        $this->expectException(QueryException::class);

        FurnitureRequest::factory()->create(['product_id' => 999999]);
    }
}
