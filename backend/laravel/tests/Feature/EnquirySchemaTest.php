<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\EnquiryStatus;
use Database\Factories\EnquiryFactory;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnquirySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('enquiries'));
        $this->assertTrue(Schema::hasColumns('enquiries', [
            'id',
            'user_id',
            'enquiry_reference',
            'product_id',
            'order_id',
            'name',
            'email',
            'phone',
            'subject',
            'message',
            'enquiry_status',
            'staff_internal_notes',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_guest_enquiry_can_exist_with_null_user(): void
    {
        $enquiry = Enquiry::factory()->guest()->create();

        $this->assertNull($enquiry->user_id);
        $this->assertNotEmpty($enquiry->name);
        $this->assertNotEmpty($enquiry->email);
    }

    public function test_authenticated_enquiry_stores_user_and_contact_snapshot(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'email' => 'original@example.com']);

        $enquiry = Enquiry::factory()->byUser($user)->create([
            'name' => 'Snapshot Name',
            'email' => 'snapshot@example.com',
        ]);

        $this->assertSame($user->id, $enquiry->user_id);
        $this->assertTrue($enquiry->user->is($user));
        $this->assertSame('Snapshot Name', $enquiry->name);
        $this->assertSame('snapshot@example.com', $enquiry->email);
    }

    public function test_enquiry_reference_is_unique_and_formatted(): void
    {
        $enquiry = Enquiry::factory()->create();

        $this->assertMatchesRegularExpression('/^ENQ-[A-Z0-9]{10}$/', $enquiry->enquiry_reference);
        $this->assertNotSame($enquiry->id, $enquiry->enquiry_reference);
    }

    public function test_duplicate_enquiry_reference_is_rejected(): void
    {
        $existing = Enquiry::factory()->create();

        $this->expectException(QueryException::class);

        Enquiry::factory()->create(['enquiry_reference' => $existing->enquiry_reference]);
    }

    public function test_enquiry_reference_is_immutable(): void
    {
        $enquiry = Enquiry::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry reference is immutable.');

        $enquiry->enquiry_reference = EnquiryFactory::generateReference();
        $enquiry->save();
    }

    public function test_creation_without_reference_and_status_assigns_server_defaults(): void
    {
        $enquiry = new Enquiry([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Do you have sofas in velvet?',
            'message' => 'Looking for a three-seat velvet sofa.',
        ]);
        $enquiry->save();

        $fresh = $enquiry->fresh();

        $this->assertMatchesRegularExpression('/^ENQ-[A-Z0-9]{10}$/', $fresh->enquiry_reference);
        $this->assertSame(EnquiryStatus::OPEN, $fresh->enquiry_status);
    }

    public function test_server_controlled_fields_are_not_mass_assignable(): void
    {
        $enquiry = new Enquiry([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Question about chairs',
            'message' => 'Inquiry message',
            'enquiry_reference' => 'ENQ-CUSTOM1234',
            'enquiry_status' => EnquiryStatus::CLOSED,
            'staff_internal_notes' => 'Unauthorized notes',
        ]);
        $enquiry->save();

        $fresh = $enquiry->fresh();

        $this->assertNotSame('ENQ-CUSTOM1234', $fresh->enquiry_reference);
        $this->assertSame(EnquiryStatus::OPEN, $fresh->enquiry_status);
        $this->assertNull($fresh->staff_internal_notes);
    }

    public function test_enquiry_reference_differs_from_order_reference_namespace(): void
    {
        $enquiry = Enquiry::factory()->create();

        $this->assertStringStartsWith('ENQ-', $enquiry->enquiry_reference);
    }

    public function test_contact_fields_persist(): void
    {
        $enquiry = Enquiry::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+255700000000',
        ]);

        $fresh = $enquiry->fresh();

        $this->assertSame('Jane Doe', $fresh->name);
        $this->assertSame('jane@example.com', $fresh->email);
        $this->assertSame('+255700000000', $fresh->phone);
    }

    public function test_enquiry_requires_name(): void
    {
        $enquiry = Enquiry::factory()->make(['name' => '   ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry name is required.');

        $enquiry->save();
    }

    public function test_enquiry_requires_at_least_one_contact_channel(): void
    {
        $enquiry = Enquiry::factory()->make(['email' => null, 'phone' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('An enquiry requires at least one of email or phone.');

        $enquiry->save();
    }

    public function test_enquiry_permits_only_email(): void
    {
        $enquiry = Enquiry::factory()->create(['phone' => null]);

        $this->assertNull($enquiry->phone);
        $this->assertNotEmpty($enquiry->email);
    }

    public function test_enquiry_permits_only_phone(): void
    {
        $enquiry = Enquiry::factory()->create(['email' => null]);

        $this->assertNull($enquiry->email);
        $this->assertNotEmpty($enquiry->phone);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $enquiry = Enquiry::factory()->make(['email' => 'not-an-email']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry email must be a valid email address.');

        $enquiry->save();
    }

    public function test_subject_and_message_persist(): void
    {
        $enquiry = Enquiry::factory()->create([
            'subject' => 'Do you have sofas in velvet?',
            'message' => 'Looking for a three-seat velvet sofa for our office.',
        ]);

        $fresh = $enquiry->fresh();

        $this->assertSame('Do you have sofas in velvet?', $fresh->subject);
        $this->assertSame('Looking for a three-seat velvet sofa for our office.', $fresh->message);
    }

    public function test_blank_subject_is_rejected(): void
    {
        $enquiry = Enquiry::factory()->make(['subject' => '   ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry subject is required.');

        $enquiry->save();
    }

    public function test_blank_message_is_rejected(): void
    {
        $enquiry = Enquiry::factory()->make(['message' => '   ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry message is required.');

        $enquiry->save();
    }

    public function test_subject_over_limit_is_rejected(): void
    {
        $enquiry = Enquiry::factory()->make(['subject' => '  '.str_repeat('a', 201).'  ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry subject is too long.');

        $enquiry->save();
    }

    public function test_message_over_limit_is_rejected(): void
    {
        $enquiry = Enquiry::factory()->make(['message' => '  '.str_repeat('b', 5001).'  ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry message is too long.');

        $enquiry->save();
    }

    public function test_general_enquiry_can_have_null_product_and_order(): void
    {
        $enquiry = Enquiry::factory()->general()->create();

        $this->assertNull($enquiry->product_id);
        $this->assertNull($enquiry->order_id);
    }

    public function test_enquiry_can_reference_a_product(): void
    {
        $product = Product::factory()->create();
        $enquiry = Enquiry::factory()->forProduct($product)->create();

        $this->assertSame($product->id, $enquiry->product_id);
        $this->assertTrue($enquiry->product->is($product));
    }

    public function test_deleting_product_nulls_product_id_without_deleting_enquiry(): void
    {
        $product = Product::factory()->create();
        $enquiry = Enquiry::factory()->forProduct($product)->create([
            'subject' => 'Material question',
            'message' => 'What wood is this made from?',
        ]);

        $product->forceDelete();

        $fresh = $enquiry->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->product_id);
        $this->assertSame('Material question', $fresh->subject);
        $this->assertSame('What wood is this made from?', $fresh->message);
    }

    public function test_enquiry_can_reference_an_order(): void
    {
        $order = Order::factory()->create();
        $enquiry = Enquiry::factory()->forOrder($order)->create();

        $this->assertSame($order->id, $enquiry->order_id);
        $this->assertTrue($enquiry->order->is($order));
    }

    public function test_deleting_order_with_enquiry_context_is_restricted(): void
    {
        $order = Order::factory()->create();
        Enquiry::factory()->forOrder($order)->create();

        $this->expectException(QueryException::class);

        $order->delete();
    }

    public function test_deleting_user_nulls_user_id_without_deleting_enquiry(): void
    {
        $user = User::factory()->create();
        $enquiry = Enquiry::factory()->byUser($user)->create();

        $user->delete();

        $fresh = $enquiry->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->user_id);
    }

    public function test_default_status_is_open(): void
    {
        $enquiry = Enquiry::factory()->create();

        $this->assertSame(EnquiryStatus::OPEN, $enquiry->enquiry_status);
    }

    public function test_closed_enquiry_status_is_persisted(): void
    {
        $enquiry = Enquiry::factory()->closed()->create();

        $this->assertSame(EnquiryStatus::CLOSED, $enquiry->fresh()->enquiry_status);
    }

    public function test_arbitrary_enquiry_status_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        Enquiry::factory()->make(['enquiry_status' => 'IN_PROGRESS']);
    }

    public function test_customer_content_is_immutable_after_submission(): void
    {
        $enquiry = Enquiry::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Enquiry subject is immutable once submitted.');

        $enquiry->subject = 'Changed';
        $enquiry->save();
    }

    public function test_historical_enquiry_untouched_by_profile_product_and_order_changes(): void
    {
        $user = User::factory()->create(['name' => 'OG', 'email' => 'og@example.com']);
        $product = Product::factory()->create(['name' => 'Original Product']);
        $order = Order::factory()->create();

        $enquiry = Enquiry::factory()->byUser($user)->forProduct($product)->forOrder($order)->create([
            'name' => 'Submitter',
            'email' => 'submitter@example.com',
            'phone' => '+255700000001',
            'subject' => 'Original subject',
            'message' => 'Original message',
        ]);

        $user->update(['name' => 'Changed User', 'email' => 'changed@example.com']);
        $product->update(['name' => 'Renamed Product']);

        $fresh = $enquiry->fresh();

        $this->assertSame('Submitter', $fresh->name);
        $this->assertSame('submitter@example.com', $fresh->email);
        $this->assertSame('+255700000001', $fresh->phone);
        $this->assertSame('Original subject', $fresh->subject);
        $this->assertSame('Original message', $fresh->message);
    }

    public function test_duplicate_contact_and_message_are_allowed(): void
    {
        $base = [
            'name' => 'Same Person',
            'email' => 'same@example.com',
            'phone' => '+255700000002',
            'subject' => 'Identical subject',
            'message' => 'Identical message text',
        ];

        $first = Enquiry::factory()->create($base);
        $second = Enquiry::factory()->create($base);

        $this->assertNotNull($first->id);
        $this->assertNotNull($second->id);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Enquiry::count());
    }

    public function test_staff_internal_notes_are_stored_separately(): void
    {
        $enquiry = Enquiry::factory()->withStaffNote()->create([
            'message' => 'Customer message',
            'staff_internal_notes' => 'Operational note',
        ]);

        $this->assertSame('Customer message', $enquiry->message);
        $this->assertSame('Operational note', $enquiry->staff_internal_notes);
    }

    public function test_no_forbidden_domain_columns_exist(): void
    {
        $columns = Schema::getColumnListing('enquiries');

        foreach (['request_id', 'payment_id', 'payment_status', 'quoted_price', 'amount', 'currency', 'inventory_id', 'warehouse_location', 'delivery_id', 'delivery_fee', 'delivery_status', 'order_status', 'total', 'tracking_number', 'password', 'staff_id', 'is_admin'] as $col) {
            $this->assertNotContains($col, $columns, "Forbidden column {$col} must not exist");
        }
    }

    public function test_enquiry_requires_valid_user_foreign_key_when_set(): void
    {
        $this->expectException(QueryException::class);

        Enquiry::factory()->create(['user_id' => 999999]);
    }

    public function test_enquiry_requires_valid_product_foreign_key_when_set(): void
    {
        $this->expectException(QueryException::class);

        Enquiry::factory()->create(['product_id' => 999999]);
    }

    public function test_enquiry_requires_valid_order_foreign_key_when_set(): void
    {
        $this->expectException(QueryException::class);

        Enquiry::factory()->create(['order_id' => 999999]);
    }
}
