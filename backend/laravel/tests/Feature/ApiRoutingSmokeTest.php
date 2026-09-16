<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\ClerkTokenVerifier;
use App\Http\Middleware\AuthenticateClerkIfPresent;
use App\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 2.6 routing-foundation smoke tests.
 *
 * These verify the transport/middleware topology only, not domain behavior:
 * stub controllers return 501; retired auth routes return 410; protected routes reject unauthenticated
 * callers with 401 via the `auth` middleware boundary; and the
 * Operational/Administrative guards deny-by-default, so an authenticated
 * (but not-yet-roleable) principal receives 403 on every protected boundary.
 */
class ApiRoutingSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const API_ADMIN = '/api/v1/admin/staff';

    private const API_PRODUCTS = '/api/v1/products';

    private const API_REQUESTS = '/api/v1/requests';

    private const API_ENQUIRIES = '/api/v1/enquiries';

    public function test_administrative_routes_deny_authenticated_non_admin_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(self::API_ADMIN)->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->actingAs($user)->postJson(self::API_ADMIN)->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/users')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/admin/products')->assertForbidden();
        $this->actingAs($user)->postJson(self::API_PRODUCTS)->assertForbidden();
        $this->actingAs($user)->patchJson('/api/v1/categories/demo-category')->assertForbidden();
    }

    public function test_operational_routes_deny_authenticated_non_staff_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/orders')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/orders/OD-1/accept')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/orders/OD-1/ship')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/inventory')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/inventory/prod-1/adjust')->assertForbidden();
        $this->actingAs($user)->getJson(self::API_REQUESTS)->assertForbidden();
        $this->actingAs($user)->getJson(self::API_ENQUIRIES)->assertForbidden();
    }

    public function test_public_catalog_routes_do_not_require_authentication(): void
    {
        $this->getJson(self::API_PRODUCTS)->assertStatus(501);
        $this->getJson(self::API_PRODUCTS.'/demo-sofa')->assertStatus(501);
        $this->getJson(self::API_PRODUCTS.'/demo-sofa/variants')->assertStatus(501);
        $this->getJson(self::API_PRODUCTS.'/demo-sofa/variants/var-1')->assertStatus(501);
        $this->getJson('/api/v1/categories')->assertStatus(501);
        $this->getJson('/api/v1/categories/demo-category')->assertStatus(501);
    }

    public function test_public_anonymous_submission_routes_do_not_require_authentication(): void
    {
        $this->postJson('/api/v1/auth/register')->assertStatus(410);
        $this->postJson('/api/v1/auth/login')->assertStatus(410);
        $this->postJson('/api/v1/auth/password/forgot')->assertStatus(410);
        $this->postJson('/api/v1/auth/password/reset')->assertStatus(410);
        $this->postJson(self::API_REQUESTS)->assertStatus(501);
        $this->postJson(self::API_ENQUIRIES)->assertStatus(501);
    }

    public function test_optional_authentication_accepts_anonymous_and_authenticated_requests(): void
    {
        $user = User::factory()->customer()->create(['clerk_user_id' => 'user_123']);
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->once()
            ->andReturn(new AuthenticatedClerkIdentity('user_123', 'sess_123', 'https://clerk.example.test'));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $this->postJson(self::API_REQUESTS)->assertStatus(501);
        $this->postJson(self::API_REQUESTS, [], ['Authorization' => 'Bearer session-token'])->assertStatus(501);
        $this->assertTrue($user->exists);
    }

    public function test_optional_authentication_rejects_a_stale_cookie(): void
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->once()->andThrow(
            new ClerkAuthenticationFailure(
                ApiErrorCode::INVALID_AUTHENTICATION,
                'The authentication credential is invalid.',
                401,
            ),
        );
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $middleware = app(AuthenticateClerkIfPresent::class);
        $request = Request::create(self::API_REQUESTS, 'POST');
        $request->cookies->set('__session', 'stale-token');

        $this->expectException(ClerkAuthenticationFailure::class);

        try {
            $middleware->handle($request, fn (Request $request) => response()->json(['ok' => true]));
        } catch (ClerkAuthenticationFailure $exception) {
            $this->assertSame('INVALID_AUTHENTICATION', $exception->errorCode()->value);
            $this->assertSame(401, $exception->status());

            throw $exception;
        }
    }

    public function test_me_returns_the_authenticated_user_envelope(): void
    {
        $user = User::factory()->customer()->create(['name' => 'Jane Customer', 'email' => 'jane@example.com']);
        $this->app->instance(ClerkTokenVerifier::class, $this->mockVerifier());
        $user->forceFill(['clerk_user_id' => 'user_123'])->save();

        $response = $this->getJson('/api/v1/me', ['Authorization' => 'Bearer session-token']);

        $response->assertOk()->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonPath('data.role', 'CUSTOMER')
            ->assertJsonPath('data.name', 'Jane Customer')
            ->assertJsonPath('data.email', 'jane@example.com');
    }

    public function test_customer_self_service_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me/orders')->assertUnauthorized();
        $this->getJson(self::API_REQUESTS)->assertUnauthorized();
        $this->getJson(self::API_ENQUIRIES)->assertUnauthorized();
        $this->getJson('/api/v1/me/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/me/cart')->assertUnauthorized();
    }

    public function test_operational_routes_do_not_become_public(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->getJson('/api/v1/orders/OD-0001')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/accept')->assertUnauthorized();
        $this->getJson('/api/v1/inventory')->assertUnauthorized();
        $this->getJson('/api/v1/inventory/inv-1')->assertUnauthorized();
        $this->postJson('/api/v1/inventory/prod-1/adjust')->assertUnauthorized();
        $this->getJson(self::API_REQUESTS)->assertUnauthorized();
        $this->getJson(self::API_ENQUIRIES)->assertUnauthorized();
    }

    public function test_administrative_routes_do_not_become_public(): void
    {
        $this->getJson('/api/v1/admin/staff')->assertUnauthorized();
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->getJson('/api/v1/admin/audit-logs')->assertUnauthorized();
        $this->postJson(self::API_PRODUCTS)->assertUnauthorized();
        $this->postJson('/api/v1/categories')->assertUnauthorized();
        $this->patchJson('/api/v1/categories/demo')->assertUnauthorized();
    }

    public function test_removed_staff_aliases_are_not_registered(): void
    {
        foreach ([
            '/api/v1/staff/orders',
            '/api/v1/staff/orders/OD-0001',
            '/api/v1/staff/inventory',
            '/api/v1/staff/products',
            '/api/v1/staff/requests',
            '/api/v1/staff/enquiries',
        ] as $path) {
            $this->getJson($path)->assertNotFound();
        }
    }

    public function test_order_state_actions_are_post_actions_not_generic_patch(): void
    {
        $this->patchJson('/api/v1/orders/OD-0001')->assertMethodNotAllowed();
        $this->postJson('/api/v1/orders/OD-0001/process')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/ready-for-pickup')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/ship')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/deliver')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/complete')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/delivery-fee')->assertUnauthorized();
    }

    public function test_versioned_routes_exist_only_under_api_v1(): void
    {
        $this->get('/v1/products')->assertNotFound();
        $this->get('/api/products')->assertNotFound();
        $this->get('/api/v2/products')->assertNotFound();
        $this->getJson(self::API_PRODUCTS)->assertStatus(501);
    }

    public function test_health_and_infrastructure_marker_are_not_domain_routes(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    private function mockVerifier(): ClerkTokenVerifier
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity('user_123', 'sess_123', 'https://clerk.example.test'));

        return $verifier;
    }
}
