<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\ClerkTokenVerifier;
use App\Models\FurnitureRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FurnitureRequestCreationApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/requests';

    protected function setUp(): void
    {
        parent::setUp();
        config(['requests.route_enabled' => true]);
        RateLimiter::clear('ip:127.0.0.1');
    }

    public function test_anonymous_submission_returns_the_frozen_201_representation(): void
    {
        $response = $this->postJson(self::URL, [
            'name' => 'Asha Mwangi',
            'phone' => '+255700000001',
            'notes' => 'Custom bookshelf request',
        ])->assertStatus(201);

        $response->assertJsonPath('data.product_id', null)
            ->assertJsonPath('data.product', null)
            ->assertJsonPath('data.quantity', null)
            ->assertJsonPath('data.name', 'Asha Mwangi')
            ->assertJsonPath('data.phone', '+255700000001')
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.notes', 'Custom bookshelf request')
            ->assertJsonPath('data.request_status', 'SUBMITTED')
            ->assertJsonPath('data.attachments', []);

        $this->assertStringStartsWith('req_', (string) $response->json('data.id'));
        $response->assertHeaderContains('Cache-Control', 'private');
        $response->assertHeaderContains('Cache-Control', 'no-store');
        $response->assertHeaderContains('Vary', 'Authorization');

        $this->assertDatabaseHas('furniture_requests', [
            'name' => 'Asha Mwangi',
            'user_id' => null,
            'message' => 'Custom bookshelf request',
            'request_status' => 'SUBMITTED',
        ]);
    }

    public function test_authenticated_customer_submission_records_server_derived_owner(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'req_customer']);

        $response = $this->withHeaders($this->authenticateAs($customer))
            ->postJson(self::URL, ['name' => 'Jane', 'email' => 'jane@example.com'])
            ->assertStatus(201);

        $response->assertJsonMissingPath('data.user_id');
        $this->assertDatabaseHas('furniture_requests', [
            'user_id' => $customer->id,
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_client_supplied_ownership_is_rejected(): void
    {
        $this->postJson(self::URL, [
            'name' => 'Asha',
            'phone' => '+255700000001',
            'user_id' => 999,
        ])->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_VALUE');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_client_supplied_status_and_reference_are_rejected(): void
    {
        foreach (['request_status' => 'CLOSED', 'request_reference' => 'REQ-ABCDEFGHIJ'] as $field => $value) {
            $this->postJson(self::URL, [
                'name' => 'Asha',
                'phone' => '+255700000001',
                $field => $value,
            ])->assertStatus(422);

            $this->assertDatabaseCount('furniture_requests', 0);
        }
    }

    public function test_schema_only_fields_are_rejected(): void
    {
        foreach (['style' => 'Modern', 'product_details' => ['product_name' => 'Sofa'], 'message' => 'raw'] as $field => $value) {
            $this->postJson(self::URL, [
                'name' => 'Asha',
                'phone' => '+255700000001',
                $field => $value,
            ])->assertStatus(422);

            $this->assertDatabaseCount('furniture_requests', 0);
        }
    }

    public function test_unknown_top_level_field_is_rejected(): void
    {
        $this->postJson(self::URL, [
            'name' => 'Asha',
            'phone' => '+255700000001',
            'role' => 'ADMIN',
        ])->assertStatus(422);

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_missing_name_is_rejected(): void
    {
        $this->postJson(self::URL, ['phone' => '+255700000001'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
    }

    public function test_at_least_one_contact_channel_is_required(): void
    {
        $this->postJson(self::URL, ['name' => 'Asha'])
            ->assertStatus(422);

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_omitted_quantity_is_not_defaulted_to_one(): void
    {
        $this->postJson(self::URL, ['name' => 'Asha', 'phone' => '+255700000001'])
            ->assertStatus(201)
            ->assertJsonPath('data.quantity', null);

        $this->assertNull(FurnitureRequest::query()->sole()->quantity);
    }

    public function test_staff_and_admin_are_forbidden(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'req_staff']);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'req_admin']);

        foreach ([$staff, $admin] as $actor) {
            $this->withHeaders($this->authenticateAs($actor))
                ->postJson(self::URL, ['name' => 'Asha', 'phone' => '+255700000001'])
                ->assertStatus(403)
                ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        }

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_invalid_bearer_is_rejected_and_not_downgraded_to_anonymous(): void
    {
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andThrow(ClerkAuthenticationFailure::invalid());

        $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
            ->postJson(self::URL, ['name' => 'Asha', 'phone' => '+255700000001'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'INVALID_AUTHENTICATION');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_invalid_product_reference_is_rejected(): void
    {
        $this->postJson(self::URL, [
            'name' => 'Asha',
            'phone' => '+255700000001',
            'product_id' => 'not-a-product',
        ])->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_VALUE');
    }

    public function test_linked_product_is_persisted_and_projected_as_a_summary(): void
    {
        $product = Product::factory()->madeToOrder()->create(['name' => 'Oak Sofa', 'slug' => 'oak-sofa']);

        $response = $this->postJson(self::URL, [
            'name' => 'Asha',
            'phone' => '+255700000001',
            'product_id' => ProductIdentifier::encode($product),
        ])->assertStatus(201);

        $response->assertJsonPath('data.product_id', ProductIdentifier::encode($product))
            ->assertJsonPath('data.product.name', 'Oak Sofa')
            ->assertJsonPath('data.product.slug', 'oak-sofa');

        $this->assertDatabaseHas('furniture_requests', ['product_id' => $product->id]);
    }

    public function test_route_is_gated_by_default(): void
    {
        config(['requests.route_enabled' => false]);
        RateLimiter::clear('ip:127.0.0.1');

        $this->postJson(self::URL, ['name' => 'Asha', 'phone' => '+255700000001'])
            ->assertStatus(501)
            ->assertJsonPath('status', 'not_implemented');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    /** @return array<string, string> */
    private function authenticateAs(User $user): array
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            (string) $user->clerk_user_id,
            'sess_test',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return ['Authorization' => 'Bearer session-token'];
    }
}
