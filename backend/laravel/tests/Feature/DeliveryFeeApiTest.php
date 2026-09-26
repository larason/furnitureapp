<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Models\AuditEvent;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderIdentifier;
use App\Support\PermissionName;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryFeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_staff_finalizes_delivery_fee_and_audits_without_creating_payment(): void
    {
        $order = Order::factory()->deliveryPending()->create(['subtotal_amount' => 170_000_000, 'total_amount' => 170_000_000]);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'delivery_fee_staff']);
        $headers = $this->authenticateAs($staff);

        $response = $this->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($this->url($order), $this->payload(2_500_000));

        $response->assertOk()
            ->assertJsonPath('data.id', OrderIdentifier::encode($order))
            ->assertJsonPath('data.delivery_fee.amount', 2_500_000)
            ->assertJsonPath('data.delivery_fee.currency', 'TZS')
            ->assertJsonPath('data.delivery_fee_status', 'FINALIZED')
            ->assertJsonPath('data.total.amount', 172_500_000);

        $fresh = $order->fresh();
        $this->assertSame(170_000_000, $fresh->subtotal_amount);
        $this->assertSame(2_500_000, $fresh->delivery_fee_amount);
        $this->assertSame(172_500_000, $fresh->total_amount);
        $this->assertSame('PENDING_PAYMENT', $fresh->status->value);
        $this->assertSame(0, $fresh->payments()->count());

        $audit = AuditEvent::query()->sole();
        $this->assertSame($staff->id, $audit->actor_id);
        $this->assertSame('DELIVERY_FEE_FINALIZED', $audit->action);
        $this->assertSame('order', $audit->resource_type);
        $this->assertNull($audit->previous_state['delivery_fee_amount']);
        $this->assertSame(2_500_000, $audit->resulting_state['delivery_fee_amount']);
    }

    public function test_zero_fee_delivery_is_valid(): void
    {
        $order = Order::factory()->deliveryPending()->create();

        $this->withHeaders($this->authenticateAs($this->createStaff()) + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($this->url($order), $this->payload(0))
            ->assertOk()
            ->assertJsonPath('data.delivery_fee.amount', 0)
            ->assertJsonPath('data.total.amount', $order->subtotal_amount);
    }

    public function test_same_key_replays_without_duplicate_audit(): void
    {
        $order = Order::factory()->deliveryPending()->create();
        $headers = $this->authenticateAs($this->createStaff());
        $key = (string) Str::uuid();

        $first = $this->withHeaders($headers + ['Idempotency-Key' => $key])
            ->postJson($this->url($order), $this->payload(35_000));
        $second = $this->withHeaders($headers + ['Idempotency-Key' => $key])
            ->postJson($this->url($order), $this->payload(35_000));

        $first->assertOk();
        $second->assertOk()->assertExactJson($first->json());
        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertSame(1, IdempotencyKey::query()->count());
    }

    public function test_same_key_with_different_fee_conflicts(): void
    {
        $order = Order::factory()->deliveryPending()->create();
        $headers = $this->authenticateAs($this->createStaff());
        $key = (string) Str::uuid();

        $this->withHeaders($headers + ['Idempotency-Key' => $key])
            ->postJson($this->url($order), $this->payload(35_000))
            ->assertOk();

        $this->withHeaders($headers + ['Idempotency-Key' => $key])
            ->postJson($this->url($order), $this->payload(36_000))
            ->assertConflict()
            ->assertJsonPath('errors.0.code', 'DUPLICATE_OPERATION');
    }

    public function test_customer_cannot_finalize_delivery_fee(): void
    {
        $order = Order::factory()->deliveryPending()->create();

        $this->withHeaders($this->authenticateAs(User::factory()->customer()->create(['clerk_user_id' => 'fee_customer'])) + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($this->url($order), $this->payload(35_000))
            ->assertForbidden();
    }

    public function test_pickup_order_is_rejected_as_business_rule(): void
    {
        $order = Order::factory()->pickup()->create();

        $this->withHeaders($this->authenticateAs($this->createStaff()) + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($this->url($order), $this->payload(35_000))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_invalid_request_shape_is_rejected_without_mutation(): void
    {
        $order = Order::factory()->deliveryPending()->create();
        $headers = $this->authenticateAs($this->createStaff()) + ['Idempotency-Key' => (string) Str::uuid()];

        $this->withHeaders($headers)->postJson($this->url($order), [
            'delivery_fee' => ['amount' => '35000', 'currency' => 'USD', 'reason' => 'zone'],
            'total' => 999,
        ])->assertUnprocessable();

        $this->assertNull($order->fresh()->delivery_fee_amount);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_finalized_order_cannot_be_changed_with_a_new_key(): void
    {
        $order = Order::factory()->deliveryFinalized(35_000)->create();

        $this->withHeaders($this->authenticateAs($this->createStaff()) + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($this->url($order), $this->payload(36_000))
            ->assertConflict()
            ->assertJsonPath('errors.0.code', 'INVALID_ORDER_TRANSITION');
    }

    public function test_noncanonical_order_identifier_is_not_accepted(): void
    {
        $order = Order::factory()->deliveryPending()->create();
        $canonical = OrderIdentifier::encode($order);
        $nonCanonical = 'ord_0'.substr($canonical, strlen('ord_'));

        $this->withHeaders($this->authenticateAs($this->createStaff()) + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/orders/'.$nonCanonical.'/delivery-fee', $this->payload(35_000))
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'ORDER_NOT_FOUND');

        $this->assertNull($order->fresh()->delivery_fee_amount);
        $this->assertSame(0, IdempotencyKey::query()->count());
    }

    public function test_staff_without_specific_permission_is_forbidden(): void
    {
        $order = Order::factory()->deliveryPending()->create();
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::ORDERS_SET_DELIVERY_FEE->value);

        $this->withHeaders($this->authenticateAs($this->createStaff()) + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($this->url($order), $this->payload(35_000))
            ->assertForbidden();
    }

    private function createStaff(): User
    {
        return User::factory()->staff()->create(['clerk_user_id' => 'fee_staff_'.Str::random(8)]);
    }

    /** @return array<string, string> */
    private function authenticateAs(User $user): array
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            $user->clerk_user_id,
            'sess_test',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return ['Authorization' => 'Bearer session-token'];
    }

    /** @return array{delivery_fee: array{amount: int, currency: string}} */
    private function payload(int $amount): array
    {
        return ['delivery_fee' => ['amount' => $amount, 'currency' => 'TZS']];
    }

    private function url(Order $order): string
    {
        return '/api/v1/orders/'.OrderIdentifier::encode($order).'/delivery-fee';
    }
}
