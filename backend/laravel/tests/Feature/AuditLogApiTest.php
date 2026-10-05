<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Models\AuditEvent;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\ProductStock;
use App\Models\User;
use App\Support\EnquiryIdentifier;
use App\Support\FurnitureRequestIdentifier;
use App\Support\InventoryIdentifier;
use App\Support\PermissionName;
use App\Support\UserIdentifier;
use Carbon\CarbonImmutable;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    private const PATH = '/api/v1/admin/audit-logs';

    public function test_only_an_admin_with_audit_view_can_list_private_audit_logs(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'audit_admin']);
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'audit_customer']);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'audit_staff']);
        $this->event($staff, occurredAt: CarbonImmutable::parse('2026-10-05T10:00:00Z'));

        $this->getJson(self::PATH)->assertUnauthorized();
        $this->asUser($customer)->getJson(self::PATH)->assertForbidden();
        $this->asUser($staff)->getJson(self::PATH)->assertForbidden();

        Role::findByName('ADMIN')->revokePermissionTo(PermissionName::AUDIT_VIEW->value);
        $this->asUser($admin)->getJson(self::PATH)->assertForbidden();
        Role::findByName('ADMIN')->givePermissionTo(PermissionName::AUDIT_VIEW->value);

        $response = $this->asUser($admin)->getJson(self::PATH)->assertOk();

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Authorization', (string) $response->headers->get('Vary'));
    }

    public function test_audit_resource_derives_an_opaque_id_and_allowlists_structured_snapshots(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'audit_admin']);
        $actor = User::factory()->staff()->create(['clerk_user_id' => 'audit_actor']);
        $event = $this->event($actor, previous: [
            'quantity' => 10,
            'reserved_quantity' => 2,
            'available_quantity' => 8,
            'warehouse_location' => 'Dar A-1',
            'quantity_delta' => 5,
            'reason' => 'STOCK_RECEIPT',
            'password_hash' => 'must-not-leak',
            'clerk_user_id' => 'must-not-leak',
            'capability_token' => 'must-not-leak',
        ], resulting: [
            'quantity' => 15,
            'reserved_quantity' => 2,
            'available_quantity' => 13,
            'warehouse_location' => 'Dar A-1',
            'quantity_delta' => 5,
            'reason' => 'STOCK_RECEIPT',
            'future_key' => 'must-not-leak',
        ]);

        $data = $this->asUser($admin)->getJson(self::PATH)->assertOk()->json('data.0');

        $this->assertSame('audit_'.base_convert((string) $event->id, 10, 36), $data['id']);
        $this->assertSame(UserIdentifier::encodeId($actor->id), $data['actor_id']);
        $this->assertSame('STAFF', $data['actor_role']);
        $this->assertSame('inventory', $data['resource_type']);
        $this->assertSame('inv_visible', $data['resource_id']);
        $this->assertSame('2026-10-05T10:00:00.000000Z', $data['timestamp']);
        $this->assertSame(['quantity', 'reserved_quantity', 'available_quantity', 'warehouse_location', 'quantity_delta', 'reason'], array_keys($data['previous_state']));
        $this->assertSame(['quantity', 'reserved_quantity', 'available_quantity', 'warehouse_location', 'quantity_delta', 'reason'], array_keys($data['resulting_state']));
        $this->assertSame('Dar A-1', $data['resulting_state']['warehouse_location']);
        $this->assertArrayNotHasKey('details', $data);
        $this->assertIsString($data['id']);
        $this->assertIsString($data['actor_id']);
        $this->assertArrayNotHasKey('audit_event_id', $data);
        $this->assertArrayNotHasKey('actor_db_id', $data);
        $this->assertStringNotContainsString('must-not-leak', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function test_filters_dates_pagination_and_order_are_strict_and_deterministic(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'audit_admin']);
        $actor = User::factory()->staff()->create(['clerk_user_id' => 'audit_actor']);
        $first = $this->event($actor, resourceId: 'inv_target', occurredAt: CarbonImmutable::parse('2026-10-04T10:00:00Z'));
        $second = $this->event($actor, resourceId: 'inv_target', occurredAt: CarbonImmutable::parse('2026-10-05T10:00:00Z'));
        $third = $this->event(null, action: 'REQUEST_STATUS_CHANGED', resourceType: 'request', resourceId: 'req_other', occurredAt: CarbonImmutable::parse('2026-10-05T10:00:00Z'));

        $query = http_build_query([
            'actor' => UserIdentifier::encodeId($actor->id),
            'action' => 'INVENTORY_ADJUSTED',
            'resource_type' => 'inventory',
            'resource_id' => 'inv_target',
            'created_from' => '2026-10-05T00:00:00Z',
            'created_to' => '2026-10-05T23:59:59Z',
            'per_page' => '1',
        ]);
        $response = $this->asUser($admin)->getJson(self::PATH.'?'.$query)->assertOk();

        $response->assertJsonPath('data.0.id', 'audit_'.base_convert((string) $second->id, 10, 36))
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.pagination.per_page', 1);

        $all = $this->asUser($admin)->getJson(self::PATH.'?per_page=3')->assertOk()->json('data');
        $this->assertSame([
            'audit_'.base_convert((string) $third->id, 10, 36),
            'audit_'.base_convert((string) $second->id, 10, 36),
            'audit_'.base_convert((string) $first->id, 10, 36),
        ], array_column($all, 'id'));

        foreach ([
            'actor_id=1', 'request_id=correlation', 'sort=timestamp', 'pageSize=10', 'page=0',
            'per_page=101', 'action=UNKNOWN', 'resource_type=unknown', 'actor=1',
            'created_from=2026-10-06T00:00:00Z&created_to=2026-10-05T00:00:00Z',
        ] as $invalidQuery) {
            $this->asUser($admin)->getJson(self::PATH.'?'.$invalidQuery)->assertUnprocessable();
        }
    }

    public function test_audit_log_routes_are_collection_only_and_read_only(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'audit_admin']);

        foreach (['postJson', 'patchJson', 'putJson', 'deleteJson'] as $method) {
            $this->asUser($admin)->{$method}(self::PATH.'/audit_1')->assertNotFound();
        }

        $this->asUser($admin)->postJson(self::PATH)->assertMethodNotAllowed();
    }

    public function test_audit_visibility_includes_events_from_inventory_request_and_enquiry_writers(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'audit_admin']);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'audit_staff']);
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();
        $staffHeaders = $this->authenticateAs($staff);

        $this->withHeaders($staffHeaders + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/inventory/'.InventoryIdentifier::encode($stock).'/adjust', [
                'quantity_delta' => 1,
                'reason' => 'STOCK_RECEIPT',
            ])->assertOk();
        $this->withHeaders($staffHeaders)->patchJson('/api/v1/requests/'.FurnitureRequestIdentifier::encode($request), [
            'request_status' => 'IN_REVIEW',
        ])->assertOk();
        $this->withHeaders($staffHeaders)->postJson('/api/v1/enquiries/'.EnquiryIdentifier::encode($enquiry).'/close')
            ->assertOk();
        $this->withHeaders($staffHeaders)->getJson(self::PATH)->assertForbidden();

        $data = $this->asUser($admin)->getJson(self::PATH)->assertOk()->json('data');

        $this->assertSame(
            ['ENQUIRY_STATUS_CHANGED', 'REQUEST_STATUS_CHANGED', 'INVENTORY_ADJUSTED'],
            array_column($data, 'action'),
        );
        $this->assertSame(
            [EnquiryIdentifier::encode($enquiry), FurnitureRequestIdentifier::encode($request), InventoryIdentifier::encode($stock)],
            array_column($data, 'resource_id'),
        );
    }

    /** @param array<string, mixed>|null $previous
     * @param  array<string, mixed>|null  $resulting
     */
    private function event(
        ?User $actor,
        string $action = 'INVENTORY_ADJUSTED',
        string $resourceType = 'inventory',
        string $resourceId = 'inv_visible',
        ?CarbonImmutable $occurredAt = null,
        ?array $previous = null,
        ?array $resulting = null,
    ): AuditEvent {
        return AuditEvent::query()->create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor === null ? null : 'STAFF',
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'previous_state' => $previous ?? ['quantity' => 10],
            'resulting_state' => $resulting ?? ['quantity' => 15],
            'request_id' => 'request-visible',
            'occurred_at' => $occurredAt ?? CarbonImmutable::parse('2026-10-05T10:00:00Z'),
        ]);
    }

    private function asUser(User $user): self
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            $user->clerk_user_id,
            'sess_123',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return $this->withHeaders(['Authorization' => 'Bearer session-token']);
    }
}
