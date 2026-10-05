<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\EnquiryCategory;
use App\Support\EnquiryIdentifier;
use App\Support\EnquiryStatus;
use App\Support\OrderIdentifier;
use App\Support\PermissionName;
use App\Support\ProductIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

final class OperationalEnquiryApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    private const ENQUIRIES_PATH = '/api/v1/enquiries/';

    private const CLOSE_ACTION = '/close';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_view_staff_can_list_and_view_enquiries_without_internal_notes(): void
    {
        $enquiry = Enquiry::factory()->withStaffNote()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_1']);
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::ENQUIRIES_MANAGE->value);
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->getJson('/api/v1/enquiries')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', EnquiryIdentifier::encode($enquiry))
            ->assertJsonPath('data.0.staff_internal_notes', null);

        $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry))
            ->assertOk()
            ->assertJsonPath('data.staff_internal_notes', null);
    }

    public function test_manage_staff_can_close_and_audit_an_enquiry(): void
    {
        $enquiry = Enquiry::factory()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_2']);
        $headers = $this->authenticateAs($staff) + ['X-Request-Id' => '8f8f3e72-3f6c-4e4f-99ce-42e9e87b2252'];

        $this->withHeaders($headers)->postJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION, [
            'staff_internal_notes' => 'Called customer.',
        ])
            ->assertOk()
            ->assertJsonPath('data.enquiry_status', 'CLOSED')
            ->assertJsonPath('data.staff_internal_notes', 'Called customer.');

        $this->assertSame('Called customer.', $enquiry->fresh()->staff_internal_notes);

        $event = AuditEvent::query()->sole();
        $this->assertSame($staff->id, $event->actor_id);
        $this->assertSame('ENQUIRY_STATUS_CHANGED', $event->action);
        $this->assertSame('enquiry', $event->resource_type);
        $this->assertSame('OPEN', $event->previous_state['enquiry_status']);
        $this->assertSame('CLOSED', $event->resulting_state['enquiry_status']);
        $this->assertSame($headers['X-Request-Id'], $event->request_id);
    }

    public function test_close_rejects_intake_and_unknown_fields(): void
    {
        $enquiry = Enquiry::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'phone' => '+255700000001',
            'subject' => 'Original subject',
            'message' => 'Original customer message.',
            'category' => EnquiryCategory::GENERAL,
        ]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_immutable']));

        foreach (['name', 'email', 'phone', 'subject', 'message', 'category', 'product_id', 'order_id', 'user_id', 'enquiry_status', 'attachment', 'created_at', 'updated_at', 'unknown'] as $field) {
            $this->withHeaders($headers)->postJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION, [$field => 'tampered'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
                ->assertJsonPath('errors.0.field', $field);
        }

        $this->assertSame('OPEN', $enquiry->fresh()->enquiry_status->value);
        $this->assertNull($enquiry->fresh()->staff_internal_notes);
        $this->assertSame('Original Name', $enquiry->fresh()->name);
        $this->assertSame('original@example.com', $enquiry->fresh()->email);
        $this->assertSame('+255700000001', $enquiry->fresh()->phone);
        $this->assertSame('Original subject', $enquiry->fresh()->subject);
        $this->assertSame('Original customer message.', $enquiry->fresh()->message);
        $this->assertSame(EnquiryCategory::GENERAL, $enquiry->fresh()->category);
    }

    public function test_close_rejects_query_parameters_and_does_not_read_notes_from_them(): void
    {
        $enquiry = Enquiry::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_query']));

        $this->withHeaders($headers)->postJson(
            self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION.'?staff_internal_notes=query-note',
        )
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
            ->assertJsonPath('errors.0.field', 'staff_internal_notes');

        $this->assertSame('OPEN', $enquiry->fresh()->enquiry_status->value);
        $this->assertNull($enquiry->fresh()->staff_internal_notes);
    }

    public function test_close_rejects_non_object_json_bodies(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_json_root']));

        foreach (['123', '"note"', '[]'] as $body) {
            $enquiry = Enquiry::factory()->create();

            $this->withHeaders($headers)
                ->call('POST', self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
                ->assertUnprocessable()
                ->assertJsonPath('errors.0.code', 'INVALID_TYPE');

            $this->assertSame('OPEN', $enquiry->fresh()->enquiry_status->value);
        }
    }

    public function test_view_only_staff_cannot_close_and_closed_close_is_idempotent(): void
    {
        $open = Enquiry::factory()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_3']);
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::ENQUIRIES_MANAGE->value);
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->post(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($open).self::CLOSE_ACTION)
            ->assertForbidden();

        Role::findByName('STAFF')->givePermissionTo(PermissionName::ENQUIRIES_MANAGE->value);
        $manager = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_4']);
        $managerHeaders = $this->authenticateAs($manager);
        $closed = Enquiry::factory()->closed()->create();

        $this->withHeaders($managerHeaders)->post(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($closed).self::CLOSE_ACTION)
            ->assertOk();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_audit_failure_rolls_back_enquiry_close(): void
    {
        $enquiry = Enquiry::factory()->create();
        $this->mock(AuditRecorder::class, function ($mock): void {
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit unavailable'));
        });
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_5']));

        $this->withHeaders($headers)->post(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION)
            ->assertStatus(500);

        $this->assertSame('OPEN', $enquiry->fresh()->enquiry_status->value);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_operational_access_requires_the_relevant_explicit_permission(): void
    {
        $enquiry = Enquiry::factory()->create();

        $this->getJson('/api/v1/enquiries')->assertUnauthorized();
        $this->getJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry))->assertUnauthorized();
        $this->postJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION)->assertUnauthorized();

        $customer = User::factory()->customer()->create(['clerk_user_id' => 'enquiry_customer_forbidden']);
        $customerHeaders = $this->authenticateAs($customer);
        $this->withHeaders($customerHeaders)->getJson('/api/v1/enquiries')->assertForbidden();
        $this->withHeaders($customerHeaders)->getJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry))->assertForbidden();
        $this->withHeaders($customerHeaders)->postJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION)->assertForbidden();

        Role::findByName('STAFF')->revokePermissionTo(PermissionName::ENQUIRIES_MANAGE->value);
        $viewStaff = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_view_only_staff']);
        $viewHeaders = $this->authenticateAs($viewStaff);
        $this->withHeaders($viewHeaders)->getJson('/api/v1/enquiries')->assertOk();
        $this->withHeaders($viewHeaders)->getJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry))->assertOk();
        $this->withHeaders($viewHeaders)->postJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION)->assertForbidden();

        $adminHeaders = $this->authenticateAs(User::factory()->admin()->create(['clerk_user_id' => 'enquiry_admin_permissions']));
        $this->withHeaders($adminHeaders)->getJson('/api/v1/enquiries')->assertOk();
        $this->withHeaders($adminHeaders)->getJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry))->assertOk();
        $this->withHeaders($adminHeaders)->post(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION)->assertOk();

        Role::findByName('STAFF')->revokePermissionTo(PermissionName::ENQUIRIES_VIEW->value);
        $unprivilegedStaffHeaders = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_no_view_staff']));
        $this->withHeaders($unprivilegedStaffHeaders)->getJson('/api/v1/enquiries')->assertForbidden();
        $this->withHeaders($unprivilegedStaffHeaders)->getJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry))->assertForbidden();
    }

    public function test_operational_filters_search_before_pagination_and_order_deterministically(): void
    {
        $product = Product::factory()->inStock()->create();
        $order = Order::factory()->create();
        $first = Enquiry::factory()->forProduct($product)->forOrder($order)->create([
            'name' => 'Filter Name',
            'email' => 'filter@example.com',
            'phone' => '+255700000101',
            'subject' => 'Filter subject',
            'message' => 'Filter message',
            'category' => EnquiryCategory::PRODUCT,
            'enquiry_status' => EnquiryStatus::OPEN,
            'created_at' => Carbon::parse('2026-10-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-10-01 10:00:00'),
        ]);
        $second = Enquiry::factory()->forProduct($product)->forOrder($order)->closed()->create([
            'name' => 'Second Name',
            'category' => EnquiryCategory::PRODUCT,
            'created_at' => Carbon::parse('2026-10-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-10-01 10:00:00'),
        ]);
        Enquiry::factory()->create([
            'category' => EnquiryCategory::DELIVERY,
            'created_at' => Carbon::parse('2026-10-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-10-02 10:00:00'),
        ]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_filter_staff']));

        foreach (['Filter Name', 'filter@example.com', '+255700000101', 'Filter subject', 'Filter message', $first->enquiry_reference] as $search) {
            $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.'?search='.urlencode($search))
                ->assertOk()
                ->assertJsonPath('meta.pagination.total', 1)
                ->assertJsonPath('data.0.id', EnquiryIdentifier::encode($first));
        }

        $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.'?search='.urlencode($order->order_reference))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonCount(2, 'data');

        $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.'?enquiry_status=OPEN&category=PRODUCT')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', EnquiryIdentifier::encode($first));

        $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.'?search=Second&enquiry_status=CLOSED')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', EnquiryIdentifier::encode($second));

        $response = $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.'?product_id='.ProductIdentifier::encode($product).'&order_id='.OrderIdentifier::encode($order).'&created_from=2026-10-01T00:00:00Z&created_to=2026-10-01T23:59:59Z&page=2&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.has_next', false)
            ->assertJsonPath('meta.pagination.has_previous', true);

        $ordered = [$first, $second];
        usort($ordered, static fn (Enquiry $left, Enquiry $right): int => $left->id <=> $right->id);
        $this->assertSame(EnquiryIdentifier::encode($ordered[1]), $response->json('data.0.id'));
    }

    public function test_operational_filters_reject_unknown_invalid_and_malformed_values(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_invalid_filter_staff']));

        foreach (['status', 'customer_id', 'assigned_to', 'priority', 'pageSize', 'sortBy', 'is_closed'] as $field) {
            $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH."?{$field}=value")
                ->assertUnprocessable()
                ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
                ->assertJsonPath('errors.0.field', $field);
        }

        foreach (['enquiry_status=closed', 'category=SUPPORT', 'product_id=123', 'order_id=123', 'created_from=not-a-date', 'created_from=2026-10-02T00:00:00Z&created_to=2026-10-01T00:00:00Z'] as $query) {
            $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH."?{$query}")
                ->assertUnprocessable();
        }
    }

    public function test_closing_an_enquiry_is_private_and_has_no_commerce_or_request_side_effects(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'enquiry_close_customer']);
        $product = Product::factory()->inStock()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $stock = ProductStock::factory()->forVariant($variant)->create(['quantity' => 5, 'reserved_quantity' => 1]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $enquiry = Enquiry::factory()->byUser($customer)->forProduct($product)->forOrder($order)->create();
        FurnitureRequest::factory()->create();
        $before = [
            'furniture_requests' => FurnitureRequest::query()->count(),
            'orders' => Order::query()->count(),
            'order_items' => DB::table('order_items')->count(),
            'payments' => Payment::query()->count(),
            'deliveries' => DB::table('deliveries')->count(),
            'product_stocks' => ProductStock::query()->count(),
        ];
        $orderState = $order->only(['status', 'subtotal_amount', 'delivery_fee_amount', 'total_amount']);
        $productState = $product->only(['product_type', 'is_active', 'is_published', 'price_amount', 'price_currency']);
        $stockState = $stock->only(['quantity', 'reserved_quantity']);
        $staffHeaders = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_close_no_commerce']));

        $this->withHeaders($staffHeaders)->postJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::CLOSE_ACTION, [
            'staff_internal_notes' => 'Private resolution note.',
        ])
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Vary', 'Authorization');

        foreach ($before as $resource => $count) {
            $this->assertSame($count, match ($resource) {
                'furniture_requests' => FurnitureRequest::query()->count(),
                'orders' => Order::query()->count(),
                'payments' => Payment::query()->count(),
                'product_stocks' => ProductStock::query()->count(),
                default => DB::table($resource)->count(),
            }, "{$resource} must not change through ENQ-006.");
        }

        $this->assertSame($orderState, $order->fresh()->only(array_keys($orderState)));
        $this->assertSame($productState, $product->fresh()->only(array_keys($productState)));
        $this->assertSame($stockState, $stock->fresh()->only(array_keys($stockState)));

        $customerHeaders = $this->authenticateAs($customer);
        $this->withHeaders($customerHeaders)->getJson('/api/v1/me/enquiries')
            ->assertOk()
            ->assertJsonMissingPath('data.0.staff_internal_notes');
        $this->withHeaders($customerHeaders)->getJson('/api/v1/me/enquiries/'.EnquiryIdentifier::encode($enquiry))
            ->assertOk()
            ->assertJsonMissingPath('data.staff_internal_notes');
    }

    public function test_operational_responses_are_private_and_mask_invalid_enquiry_identifiers(): void
    {
        $enquiry = Enquiry::factory()->withStaffNote()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_private_cache_staff']));

        $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH)
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Vary', 'Authorization');
        $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry))
            ->assertOk()
            ->assertJsonPath('data.staff_internal_notes', $enquiry->staff_internal_notes)
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Vary', 'Authorization');

        foreach (['enq_unknown', '123'] as $identifier) {
            $this->withHeaders($headers)->getJson(self::ENQUIRIES_PATH.$identifier)
                ->assertNotFound()
                ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        }
    }
}
