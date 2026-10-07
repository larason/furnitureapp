<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\FurnitureRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\FurnitureRequestIdentifier;
use App\Support\PermissionName;
use App\Support\ProductIdentifier;
use App\Support\RequestStatus;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

final class OperationalFurnitureRequestApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    private const INDEX = '/api/v1/requests';

    private const STAFF_NOTE = 'Called customer.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_anonymous_and_customer_cannot_access_operational_requests(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->getJson(self::INDEX)->assertUnauthorized();
        $this->getJson($this->url($request))->assertUnauthorized();
        $this->patchJson($this->url($request), ['staff_internal_notes' => 'No'])->assertUnauthorized();

        $customerHeaders = $this->authenticateAs(User::factory()->customer()->create(['clerk_user_id' => 'customer_10']));
        $this->withHeaders($customerHeaders)->getJson(self::INDEX)->assertForbidden();
        $this->withHeaders($customerHeaders)->getJson($this->url($request))->assertForbidden();
        $this->withHeaders($customerHeaders)->patchJson($this->url($request), ['staff_internal_notes' => 'No'])->assertForbidden();
    }

    public function test_staff_permissions_are_separate_for_view_and_manage(): void
    {
        $request = FurnitureRequest::factory()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_10']);
        $headers = $this->authenticateAs($staff);

        Role::findByName('STAFF')->revokePermissionTo(PermissionName::REQUESTS_MANAGE->value);

        $this->withHeaders($headers)->getJson(self::INDEX)->assertOk();
        $this->withHeaders($headers)->getJson($this->url($request))->assertOk();
        $this->withHeaders($headers)->patchJson($this->url($request), ['staff_internal_notes' => 'No'])->assertForbidden();
    }

    public function test_admin_can_manage_requests_and_actor_without_view_permission_is_denied(): void
    {
        $request = FurnitureRequest::factory()->create();
        $adminHeaders = $this->authenticateAs(User::factory()->admin()->create(['clerk_user_id' => 'admin_requests']));

        $this->withHeaders($adminHeaders)->getJson(self::INDEX)->assertOk();
        $this->withHeaders($adminHeaders)->getJson($this->url($request))->assertOk();
        $this->withHeaders($adminHeaders)->patchJson($this->url($request), ['staff_internal_notes' => self::STAFF_NOTE])->assertOk();

        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_without_request_view']);
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::REQUESTS_VIEW->value);
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->getJson(self::INDEX)->assertForbidden();
        $this->withHeaders($headers)->getJson($this->url($request))->assertForbidden();
    }

    public function test_staff_with_manage_permission_can_update_status_and_internal_notes_atomically(): void
    {
        $request = FurnitureRequest::factory()->create(['message' => 'Customer notes']);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_11']));

        $this->withHeaders($headers)->patchJson($this->url($request), [
            'request_status' => 'IN_REVIEW',
            'staff_internal_notes' => self::STAFF_NOTE,
        ])
            ->assertOk()
            ->assertJsonPath('data.request_status', 'IN_REVIEW')
            ->assertJsonPath('data.staff_internal_notes', self::STAFF_NOTE)
            ->assertJsonPath('data.notes', 'Customer notes');

        $this->assertSame(RequestStatus::IN_REVIEW, $request->fresh()->request_status);
        $this->assertSame(self::STAFF_NOTE, $request->fresh()->staff_internal_notes);
    }

    public function test_status_change_creates_a_durable_audit_event(): void
    {
        $request = FurnitureRequest::factory()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_audit_1']);
        $headers = $this->authenticateAs($staff) + ['X-Request-Id' => '8f8f3e72-3f6c-4e4f-99ce-42e9e87b2251'];

        $this->withHeaders($headers)->patchJson($this->url($request), [
            'request_status' => 'IN_REVIEW',
        ])->assertOk();

        $event = AuditEvent::query()->sole();
        $this->assertSame($staff->id, $event->actor_id);
        $this->assertSame('STAFF', $event->actor_role);
        $this->assertSame('REQUEST_STATUS_CHANGED', $event->action);
        $this->assertSame('request', $event->resource_type);
        $this->assertSame(FurnitureRequestIdentifier::encode($request), $event->resource_id);
        $this->assertSame('SUBMITTED', $event->previous_state['request_status']);
        $this->assertSame('IN_REVIEW', $event->resulting_state['request_status']);
        $this->assertSame($headers['X-Request-Id'], $event->request_id);
    }

    public function test_same_status_update_does_not_create_a_transition_audit_event(): void
    {
        $request = FurnitureRequest::factory()->inReview()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_audit_2']));

        $this->withHeaders($headers)->patchJson($this->url($request), [
            'request_status' => 'IN_REVIEW',
        ])->assertOk();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_direct_close_creates_a_durable_audit_event(): void
    {
        $request = FurnitureRequest::factory()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_audit_4']);
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->patchJson($this->url($request), [
            'request_status' => 'CLOSED',
        ])->assertOk();

        $event = AuditEvent::query()->sole();
        $this->assertSame('SUBMITTED', $event->previous_state['request_status']);
        $this->assertSame('CLOSED', $event->resulting_state['request_status']);
    }

    public function test_audit_failure_rolls_back_request_status_change(): void
    {
        $request = FurnitureRequest::factory()->create();
        $this->mock(AuditRecorder::class, function ($mock): void {
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit unavailable'));
        });
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_audit_3']));

        $this->withHeaders($headers)->patchJson($this->url($request), [
            'request_status' => 'IN_REVIEW',
        ])->assertStatus(500);

        $this->assertSame(RequestStatus::SUBMITTED, $request->fresh()->request_status);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_invalid_status_does_not_commit_new_internal_notes(): void
    {
        $request = FurnitureRequest::factory()->closed()->create(['staff_internal_notes' => 'Existing note']);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_12']));

        $this->withHeaders($headers)->patchJson($this->url($request), [
            'request_status' => 'IN_REVIEW',
            'staff_internal_notes' => 'Must not persist',
        ])
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CONFLICT')
            ->assertJsonPath('errors.0.field', 'request_status');

        $fresh = $request->fresh();
        $this->assertSame(RequestStatus::CLOSED, $fresh->request_status);
        $this->assertSame('Existing note', $fresh->staff_internal_notes);
    }

    public function test_notes_can_be_cleared_without_changing_customer_notes(): void
    {
        $request = FurnitureRequest::factory()->inReview()->create([
            'message' => 'Original intake',
            'staff_internal_notes' => 'Private note',
        ]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_13']));

        $this->withHeaders($headers)->patchJson($this->url($request), ['staff_internal_notes' => null])
            ->assertOk()
            ->assertJsonPath('data.staff_internal_notes', null)
            ->assertJsonPath('data.notes', 'Original intake');
    }

    public function test_unknown_and_immutable_update_fields_are_rejected(): void
    {
        $request = FurnitureRequest::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_14']));

        foreach (['name', 'product_id', 'notes', 'user_id', 'order_id', 'quoted_price'] as $field) {
            $this->withHeaders($headers)->patchJson($this->url($request), [$field => 'tampered'])
                ->assertStatus(422)
                ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
                ->assertJsonPath('errors.0.field', $field);
        }
    }

    public function test_operational_detail_masks_unknown_and_non_opaque_identifiers(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_request_identifiers']));

        foreach (['req_unknown', '123'] as $identifier) {
            $this->withHeaders($headers)->getJson(self::INDEX.'/'.$identifier)
                ->assertNotFound()
                ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_operational_collection_filters_searches_sorts_and_paginates(): void
    {
        $product = Product::factory()->madeToOrder()->create(['name' => 'Oak Desk', 'slug' => 'oak-desk']);
        $older = FurnitureRequest::factory()->forProduct($product)->create([
            'name' => 'Asha Older',
            'request_status' => RequestStatus::SUBMITTED,
            'created_at' => Carbon::parse('2026-01-01 00:00:00'),
            'updated_at' => Carbon::parse('2026-01-01 00:00:00'),
        ]);
        $newer = FurnitureRequest::factory()->forProduct($product)->inReview()->create([
            'name' => 'Asha Newer',
            'created_at' => Carbon::parse('2026-01-02 00:00:00'),
            'updated_at' => Carbon::parse('2026-01-02 00:00:00'),
        ]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_15']));

        $response = $this->withHeaders($headers)->getJson(self::INDEX.'?search=Oak%20Desk&request_status=IN_REVIEW&product_id='.ProductIdentifier::encode($product).'&page=1&per_page=1')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', FurnitureRequestIdentifier::encode($newer))
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->assertSame($newer->id, FurnitureRequestIdentifier::decode($response->json('data.0.id')));
        $this->assertNotSame($older->id, $newer->id);
    }

    public function test_operational_collection_searches_contact_reference_product_and_date_filters(): void
    {
        $product = Product::factory()->madeToOrder()->create(['name' => 'Custom Oak Table']);
        $matching = FurnitureRequest::factory()->forProduct($product)->create([
            'name' => 'Amara',
            'email' => 'amara@example.com',
            'phone' => '+255700000101',
            'created_at' => Carbon::parse('2026-02-02 12:00:00'),
        ]);
        FurnitureRequest::factory()->create([
            'name' => 'Unrelated Customer',
            'email' => 'unrelated@example.test',
            'phone' => '+255700000999',
            'created_at' => Carbon::parse('2026-02-03 12:00:00'),
        ]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_request_filters']));

        foreach (['Amara', 'amara@example.com', '+255700000101', $matching->request_reference, 'Custom Oak Table'] as $search) {
            $this->withHeaders($headers)->getJson(self::INDEX.'?search='.urlencode($search))
                ->assertOk()
                ->assertJsonPath('data.0.id', FurnitureRequestIdentifier::encode($matching));
        }

        $this->withHeaders($headers)->getJson(self::INDEX.'?created_from=2026-02-02T00:00:00Z&created_to=2026-02-02T23:59:59Z')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', FurnitureRequestIdentifier::encode($matching));
    }

    public function test_operational_updates_do_not_create_commerce_records(): void
    {
        $request = FurnitureRequest::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_request_no_commerce']));
        $before = [
            'orders' => DB::table('orders')->count(),
            'order_items' => DB::table('order_items')->count(),
            'payments' => DB::table('payments')->count(),
            'deliveries' => DB::table('deliveries')->count(),
            'product_stocks' => DB::table('product_stocks')->count(),
        ];

        $this->withHeaders($headers)->patchJson($this->url($request), ['request_status' => 'IN_REVIEW'])->assertOk();
        $this->withHeaders($headers)->patchJson($this->url($request), ['staff_internal_notes' => self::STAFF_NOTE])->assertOk();
        $this->withHeaders($headers)->patchJson($this->url($request), ['request_status' => 'CLOSED'])->assertOk();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} must not change through REQ-006.");
        }
    }

    public function test_oversized_pagination_values_are_rejected_before_integer_cast(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_17']));

        foreach (['page', 'per_page'] as $field) {
            $this->withHeaders($headers)->getJson(self::INDEX."?{$field}=1000000000")
                ->assertStatus(422)
                ->assertJsonPath('errors.0.code', 'INVALID_TYPE')
                ->assertJsonPath('errors.0.field', $field);
        }
    }

    public function test_empty_page_beyond_last_preserves_requested_page_metadata(): void
    {
        FurnitureRequest::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_18']));

        $this->withHeaders($headers)->getJson(self::INDEX.'?page=2&per_page=1')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.last_page', 1)
            ->assertJsonPath('meta.pagination.has_next', false)
            ->assertJsonPath('meta.pagination.has_previous', true);
    }

    public function test_operational_resource_hides_internal_notes_for_view_only_and_excludes_storage_internals(): void
    {
        $request = FurnitureRequest::factory()->create(['staff_internal_notes' => 'Private']);
        User::factory()->staff()->create(['clerk_user_id' => 'staff_16']);
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::REQUESTS_MANAGE->value);
        $staff = User::where('clerk_user_id', 'staff_16')->firstOrFail();
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->getJson($this->url($request))
            ->assertOk()
            ->assertJsonPath('data.staff_internal_notes', null)
            ->assertJsonPath('data.user_id', null)
            ->assertJsonMissingPath('data.storage_key')
            ->assertJsonMissingPath('data.disk');
    }

    public function test_malformed_queries_and_empty_updates_are_rejected(): void
    {
        $request = FurnitureRequest::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'staff_17']));

        $this->withHeaders($headers)->getJson(self::INDEX.'?user_id=1')
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
            ->assertJsonPath('errors.0.field', 'user_id');
        $this->withHeaders($headers)->getJson(self::INDEX.'?created_from=not-a-date')
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_FORMAT');
        $this->withHeaders($headers)->patchJson($this->url($request), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
    }

    private function url(FurnitureRequest $request): string
    {
        return self::INDEX.'/'.FurnitureRequestIdentifier::encode($request);
    }
}
