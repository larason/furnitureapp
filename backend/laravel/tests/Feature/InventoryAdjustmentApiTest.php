<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\InventoryIdentifier;
use App\Support\PermissionName;
use App\Support\ProductType;
use App\Support\RoleName;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

class InventoryAdjustmentApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_anonymous_requests_are_rejected_with_authentication_error(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);

        $this->postJson($this->url($stock), $this->payload(5))->assertUnauthorized();
        $this->postJson($this->url($stock), $this->payload(5), ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_customer_cannot_adjust_inventory(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'customer_1']);

        $this->adjust($this->authenticateAs($customer), $stock, 5)->assertForbidden();
        $this->assertSame(10, $stock->fresh()->quantity);
    }

    public function test_staff_without_inventory_manage_permission_is_forbidden(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        Role::findByName(RoleName::STAFF->value)->revokePermissionTo(PermissionName::INVENTORY_MANAGE->value);

        $this->adjust($this->authenticateAs($this->createStaff()), $stock, 5)->assertForbidden();
        $this->assertSame(10, $stock->fresh()->quantity);
    }

    public function test_staff_and_admin_with_permission_can_adjust(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 2]);

        $this->adjust($this->authenticateAs($this->createStaff()), $stock, 5)
            ->assertOk()
            ->assertJsonPath('data.quantity', 15)
            ->assertJsonPath('data.reserved_quantity', 2)
            ->assertJsonPath('data.available_quantity', 13);

        $other = ProductStock::factory()->create(['quantity' => 3, 'reserved_quantity' => 0]);
        $this->adjust($this->authenticateAs($this->createAdmin()), $other, 2)->assertOk();
    }

    public function test_independent_adjustments_are_serialized_not_conflicting(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 100, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->adjust($headers, $stock, 10, 'STOCK_RECEIPT')->assertOk()->assertJsonPath('data.quantity', 110);
        $this->adjust($headers, $stock, 10, 'STOCK_RECEIPT')->assertOk()->assertJsonPath('data.quantity', 120);

        $this->assertSame(120, $stock->fresh()->quantity);
        $this->assertSame(2, AuditEvent::query()->count());
    }

    public function test_positive_receipt_and_damage_and_return_and_correction(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 2]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->adjust($headers, $stock, 5, 'STOCK_RECEIPT')->assertOk()->assertJsonPath('data.quantity', 15);
        $this->adjust($headers, $stock, -3, 'DAMAGE')->assertOk()->assertJsonPath('data.quantity', 12);
        $this->adjust($headers, $stock, 2, 'RETURN')->assertOk()->assertJsonPath('data.quantity', 14);
        $this->adjust($headers, $stock, -1, 'CORRECTION')->assertOk()->assertJsonPath('data.quantity', 13);
        $this->adjust($headers, $stock, 1, 'CORRECTION')->assertOk()->assertJsonPath('data.quantity', 14);
        $this->adjust($headers, $stock, -2, 'AUDIT_ADJUSTMENT')->assertOk()->assertJsonPath('data.quantity', 12);
        $this->adjust($headers, $stock, 2, 'AUDIT_ADJUSTMENT')->assertOk()->assertJsonPath('data.quantity', 14);

        $this->assertSame(2, $stock->fresh()->reserved_quantity);
    }

    public function test_reason_direction_mismatch_is_rejected(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());

        foreach ([[5, 'DAMAGE'], [-5, 'STOCK_RECEIPT'], [-2, 'RETURN']] as [$delta, $reason]) {
            $this->adjust($headers, $stock, $delta, $reason)
                ->assertUnprocessable()
                ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
                ->assertJsonPath('errors.0.field', 'quantity_delta');
        }

        $this->assertSame(10, $stock->fresh()->quantity);
    }

    public function test_unknown_reason_is_rejected(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);

        $this->adjust($this->authenticateAs($this->createStaff()), $stock, 5, 'PURCHASE')
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'reason');
    }

    public function test_zero_and_malformed_deltas_are_rejected(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->adjust($headers, $stock, 0)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'quantity_delta');

        foreach (['10', 10.5, null, []] as $delta) {
            $this->adjust($headers, $stock, $delta)->assertUnprocessable();
        }

        $this->adjust($headers, $stock, null, 'STOCK_RECEIPT', missingDelta: true)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
    }

    public function test_negative_and_below_reserved_results_are_rejected_without_mutation(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 3, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->adjust($headers, $stock, -4, 'CORRECTION')->assertUnprocessable();
        $this->assertSame(3, $stock->fresh()->quantity);

        $reserved = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 7]);
        $this->adjust($headers, $reserved, -4, 'CORRECTION')->assertUnprocessable();
        $this->assertSame(10, $reserved->fresh()->quantity);
        $this->assertSame(7, $reserved->fresh()->reserved_quantity);
    }

    public function test_delta_exceeding_supported_quantity_range_is_rejected(): void
    {
        $headers = $this->authenticateAs($this->createStaff());

        $overflow = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $this->adjust($headers, $overflow, PHP_INT_MAX, 'CORRECTION')
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
            ->assertJsonPath('errors.0.field', 'quantity_delta');
        $this->assertSame(10, $overflow->fresh()->quantity);

        $beyondMaximum = ProductStock::factory()->create(['quantity' => 4_294_967_290, 'reserved_quantity' => 0]);
        $this->adjust($headers, $beyondMaximum, 10, 'CORRECTION')->assertUnprocessable();
        $this->assertSame(4_294_967_290, $beyondMaximum->fresh()->quantity);

        $boundary = ProductStock::factory()->create(['quantity' => 0, 'reserved_quantity' => 0]);
        $this->adjust($headers, $boundary, 4_294_967_295, 'STOCK_RECEIPT')
            ->assertOk()
            ->assertJsonPath('data.quantity', 4_294_967_295);
    }

    public function test_reserved_boundary_and_zero_stock_row_are_valid(): void
    {
        $reserved = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 7]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->adjust($headers, $reserved, -3, 'CORRECTION')
            ->assertOk()
            ->assertJsonPath('data.quantity', 7)
            ->assertJsonPath('data.reserved_quantity', 7)
            ->assertJsonPath('data.available_quantity', 0);

        $empty = ProductStock::factory()->create(['quantity' => 4, 'reserved_quantity' => 0]);
        $this->adjust($headers, $empty, -4, 'CORRECTION')
            ->assertOk()
            ->assertJsonPath('data.quantity', 0);
        $this->assertDatabaseHas('product_stocks', ['id' => $empty->id, 'quantity' => 0]);
    }

    public function test_unknown_inventory_and_cross_resource_identifier_return_not_found(): void
    {
        $stock = ProductStock::factory()->create();
        $headers = $this->authenticateAs($this->createStaff());

        foreach (['inv_missing', (string) $stock->id, 'prod_1', 'var_1'] as $identifier) {
            $this->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])
                ->postJson('/api/v1/inventory/'.$identifier.'/adjust', $this->payload(1))
                ->assertNotFound()
                ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_unknown_and_immutable_fields_are_rejected(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());

        foreach ([
            'quantity', 'available_quantity', 'reserved_quantity',
            'product_id', 'variant_id', 'warehouse_location', 'actor_id',
        ] as $field) {
            $this->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])
                ->postJson($this->url($stock), $this->payload(5) + [$field => 999])
                ->assertUnprocessable()
                ->assertJsonPath('errors.0.code', 'INVALID_VALUE');
        }

        $this->assertSame(10, $stock->fresh()->quantity);
    }

    public function test_missing_or_malformed_idempotency_key_is_rejected_before_mutation(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->postJson($this->url($stock), $this->payload(5))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD')
            ->assertJsonPath('errors.0.field', 'Idempotency-Key');

        $this->withHeaders($headers + ['Idempotency-Key' => 'not-a-uuid'])
            ->postJson($this->url($stock), $this->payload(5))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_FORMAT');

        $this->assertSame(10, $stock->fresh()->quantity);
    }

    public function test_idempotent_replay_does_not_double_adjust_or_duplicate_audit(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 2]);
        $headers = $this->authenticateAs($this->createStaff());
        $key = (string) Str::uuid();

        $this->withHeaders($headers + ['Idempotency-Key' => $key])->postJson($this->url($stock), $this->payload(10, 'STOCK_RECEIPT'))
            ->assertOk()
            ->assertJsonPath('data.quantity', 20);

        $this->withHeaders($headers + ['Idempotency-Key' => $key])->postJson($this->url($stock), $this->payload(10, 'STOCK_RECEIPT'))
            ->assertOk()
            ->assertJsonPath('data.quantity', 20);

        $this->assertSame(20, $stock->fresh()->quantity);
        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertSame(1, IdempotencyKey::query()->count());
    }

    public function test_same_key_with_different_intent_conflicts(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());
        $key = (string) Str::uuid();

        $this->withHeaders($headers + ['Idempotency-Key' => $key])->postJson($this->url($stock), $this->payload(5, 'STOCK_RECEIPT'))->assertOk();

        $this->withHeaders($headers + ['Idempotency-Key' => $key])->postJson($this->url($stock), $this->payload(7, 'STOCK_RECEIPT'))
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'DUPLICATE_OPERATION');

        $this->withHeaders($headers + ['Idempotency-Key' => $key])->postJson($this->url($stock), $this->payload(5, 'CORRECTION'))
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'DUPLICATE_OPERATION');

        $this->assertSame(15, $stock->fresh()->quantity);
    }

    public function test_same_key_does_not_replay_across_inventory_or_across_actor(): void
    {
        $first = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $second = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());
        $key = (string) Str::uuid();

        $this->withHeaders($headers + ['Idempotency-Key' => $key])->postJson($this->url($first), $this->payload(5, 'STOCK_RECEIPT'))->assertOk();

        $this->withHeaders($headers + ['Idempotency-Key' => $key])->postJson($this->url($second), $this->payload(5, 'STOCK_RECEIPT'))
            ->assertStatus(409);
        $this->assertSame(10, $second->fresh()->quantity);

        $otherActor = $this->authenticateAs($this->createStaff('staff_2'));
        $this->withHeaders($otherActor + ['Idempotency-Key' => $key])->postJson($this->url($first), $this->payload(5, 'STOCK_RECEIPT'))
            ->assertOk();
        $this->assertSame(20, $first->fresh()->quantity);
    }

    public function test_audit_event_is_written_with_server_derived_actor_and_state(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 2]);
        $staff = $this->createStaff();

        $this->adjust($this->authenticateAs($staff), $stock, 5, 'STOCK_RECEIPT')->assertOk();

        $event = AuditEvent::query()->sole();
        $this->assertSame($staff->id, $event->actor_id);
        $this->assertSame('STAFF', $event->actor_role);
        $this->assertSame('INVENTORY_ADJUSTED', $event->action);
        $this->assertSame('inventory', $event->resource_type);
        $this->assertSame(InventoryIdentifier::encode($stock), $event->resource_id);
        $this->assertSame(10, $event->previous_state['quantity']);
        $this->assertSame(15, $event->resulting_state['quantity']);
        $this->assertSame(5, $event->resulting_state['quantity_delta']);
        $this->assertSame('STOCK_RECEIPT', $event->resulting_state['reason']);
    }

    public function test_audit_actor_immutability_prevents_actor_deletion(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 5, 'reserved_quantity' => 0]);
        $staff = $this->createStaff();

        $this->adjust($this->authenticateAs($staff), $stock, 1, 'STOCK_RECEIPT')->assertOk();
        $this->assertSame(1, AuditEvent::query()->count());

        $this->expectException(QueryException::class);
        $staff->forceDelete();
    }

    public function test_audit_failure_rolls_back_inventory_change(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $this->mock(AuditRecorder::class, function ($mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('audit unavailable'));
        });

        $this->adjust($this->authenticateAs($this->createStaff()), $stock, 5)->assertStatus(500);

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_read_model_and_public_availability_reflect_mutation(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'adjustable-sofa',
            'product_type' => ProductType::IN_STOCK,
            'is_active' => true,
            'is_published' => true,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        $stock = ProductStock::factory()->forVariant($variant)->create(['quantity' => 0, 'reserved_quantity' => 0]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->adjust($headers, $stock, 6, 'STOCK_RECEIPT')->assertOk();
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.stock_indicator', 'IN_STOCK');

        $this->adjust($headers, $stock, -1, 'CORRECTION')->assertOk();
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.stock_indicator', 'LOW_STOCK');

        $this->adjust($headers, $stock, -5, 'CORRECTION')->assertOk();
        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable')
            ->assertJsonMissingPath('data.quantity')
            ->assertJsonMissingPath('data.variants.0.quantity')
            ->assertJsonMissing(['warehouse_location' => ProductStock::DEFAULT_LOCATION]);

        $this->withHeaders($headers)->getJson('/api/v1/inventory/'.InventoryIdentifier::encode($stock))
            ->assertOk()
            ->assertJsonPath('data.quantity', 0);

        $this->assertTrue($product->fresh()->is_active);
        $this->assertTrue($product->fresh()->is_published);
        $this->assertTrue($variant->fresh()->is_active);
    }

    public function test_adjust_route_uses_the_operational_rate_limiter(): void
    {
        $route = Route::getRoutes()->getByName('api.inventory.adjust');

        $this->assertNotNull($route);
        $this->assertContains('throttle:inventory-adjust', $route->gatherMiddleware());
        $this->assertContains('permission:inventory.manage', $route->gatherMiddleware());
    }

    private function createStaff(string $clerkUserId = 'staff_1'): User
    {
        return User::factory()->staff()->create(['clerk_user_id' => $clerkUserId]);
    }

    private function createAdmin(): User
    {
        return User::factory()->admin()->create(['clerk_user_id' => 'admin_1']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int|string|float|array|null $delta, string $reason = 'STOCK_RECEIPT'): array
    {
        return ['quantity_delta' => $delta, 'reason' => $reason];
    }

    private function url(ProductStock $stock): string
    {
        return '/api/v1/inventory/'.InventoryIdentifier::encode($stock).'/adjust';
    }

    /** @param array<string, string> $headers */
    private function adjust(array $headers, ProductStock $stock, int|string|float|array|null $delta, string $reason = 'STOCK_RECEIPT', bool $missingDelta = false): TestResponse
    {
        $body = $missingDelta ? ['reason' => $reason] : $this->payload($delta, $reason);

        return $this->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($this->url($stock), $body);
    }
}
