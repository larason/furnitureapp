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
 * callers with 401 via the `clerk.auth` middleware boundary; and the
 * Operational/Administrative guards enforce the canonical Laravel roles, so
 * an authenticated principal without the required role receives 403.
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
        $this->configureClerkUser($user = User::factory()->create(['clerk_user_id' => 'user_customer']));
        $headers = ['Authorization' => 'Bearer session-token'];

        $this->withHeaders($headers)->getJson(self::API_ADMIN)->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->withHeaders($headers)->postJson(self::API_ADMIN)->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/v1/users')->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/v1/admin/products')->assertForbidden();
        $this->withHeaders($headers)->postJson(self::API_PRODUCTS)->assertForbidden();
        $this->withHeaders($headers)->patchJson('/api/v1/categories/demo-category')->assertForbidden();
    }

    public function test_operational_routes_deny_authenticated_non_staff_users(): void
    {
        $this->configureClerkUser($user = User::factory()->create(['clerk_user_id' => 'user_customer']));
        $headers = ['Authorization' => 'Bearer session-token'];

        $this->withHeaders($headers)->getJson('/api/v1/orders')->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/v1/orders/OD-1/accept')->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/v1/orders/OD-1/ship')->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/v1/inventory')->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/v1/inventory/prod-1/adjust')->assertForbidden();
        $this->withHeaders($headers)->getJson(self::API_REQUESTS)->assertForbidden();
        $this->withHeaders($headers)->getJson(self::API_ENQUIRIES)->assertForbidden();
    }

    public function test_operational_routes_deny_pending_and_suspended_staff(): void
    {
        foreach (['PENDING', 'SUSPENDED'] as $state) {
            $staff = User::factory()->staff()->create([
                'clerk_user_id' => "staff_{$state}",
                'account_state' => $state,
            ]);
            $this->configureClerkUser($staff);

            $this->withHeaders(['Authorization' => 'Bearer session-token'])
                ->getJson('/api/v1/orders')
                ->assertForbidden();
        }
    }

    public function test_administrative_routes_deny_pending_and_suspended_admin(): void
    {
        foreach (['PENDING', 'SUSPENDED'] as $state) {
            $admin = User::factory()->admin()->create([
                'clerk_user_id' => "admin_{$state}",
                'account_state' => $state,
            ]);
            $this->configureClerkUser($admin);

            $this->withHeaders(['Authorization' => 'Bearer session-token'])
                ->getJson('/api/v1/admin/staff')
                ->assertForbidden();
        }
    }

    public function test_public_catalog_routes_do_not_require_authentication(): void
    {
        $this->getJson(self::API_PRODUCTS)->assertOk();
        $this->getJson(self::API_PRODUCTS.'/demo-sofa')->assertNotFound();
        $this->getJson(self::API_PRODUCTS.'/demo-sofa/variants')->assertNotFound();
        $this->getJson(self::API_PRODUCTS.'/demo-sofa/variants/var-1')->assertNotFound();
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/categories/demo-category')->assertNotFound();
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

    public function test_attachment_stubs_require_authentication_until_upload_scope_is_implemented(): void
    {
        $this->postJson('/api/v1/requests/REQ-1/attachments')->assertUnauthorized();
        $this->postJson('/api/v1/enquiries/ENQ-1/attachments')->assertUnauthorized();
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

    public function test_optional_authentication_is_bearer_only_and_ignores_cookies(): void
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

        // A __session cookie is not a Laravel credential: the request is
        // treated as anonymous and the verifier is never called.
        $middleware = app(AuthenticateClerkIfPresent::class);
        $request = Request::create(self::API_REQUESTS, 'POST');
        $request->cookies->set('__session', 'stale-token');

        $response = $middleware->handle($request, fn (Request $request) => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());

        // A stale bearer token is rejected.
        $this->expectException(ClerkAuthenticationFailure::class);

        try {
            $middleware->handle(
                Request::create(self::API_REQUESTS, 'POST', [], [], [],
                    ['HTTP_AUTHORIZATION' => 'Bearer stale-token']),
                fn (Request $request) => response()->json(['ok' => true]),
            );
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
        $this->getJson(self::API_PRODUCTS)->assertOk();
    }

    public function test_health_and_infrastructure_marker_are_not_domain_routes(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    private function mockVerifier(string $clerkUserId = 'user_123'): ClerkTokenVerifier
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity($clerkUserId, 'sess_123', 'https://clerk.example.test'));

        return $verifier;
    }

    private function configureClerkUser(User $user): void
    {
        $this->app->instance(ClerkTokenVerifier::class, $this->mockVerifier($user->clerk_user_id));
    }
}
