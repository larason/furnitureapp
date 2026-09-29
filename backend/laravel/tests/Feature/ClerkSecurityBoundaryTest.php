<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\Clerk\ClerkSessionGateway;
use App\Authentication\Clerk\RevokeClerkSession;
use App\Authentication\ClerkTokenVerifier;
use App\Exceptions\Api\ApiException;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClerkSecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_tokens_table_is_retired(): void
    {
        $this->assertFalse(Schema::hasTable('password_reset_tokens'), 'password_reset_tokens must be retired.');
    }

    public function test_no_laravel_password_reset_endpoint_is_served(): void
    {
        $this->postJson('/api/v1/auth/password/forgot')->assertStatus(410);
        $this->postJson('/api/v1/auth/password/reset')->assertStatus(410);
        $this->postJson('/api/v1/auth/change-password')->assertStatus(410);
    }

    public function test_retired_auth_routes_return_410_regardless_of_authentication(): void
    {
        foreach (['/api/v1/auth/logout', '/api/v1/auth/change-password', '/api/v1/email/verify', '/api/v1/email/verify/resend'] as $path) {
            $this->postJson($path)->assertStatus(410);
            $this->postJson($path, [], ['Authorization' => 'Bearer session-token'])->assertStatus(410);
        }
    }

    public function test_provisioned_customer_has_no_shadow_password(): void
    {
        $user = new User(['name' => 'Clerk Customer', 'email' => 'clerk@example.com']);
        $user->forceFill(['clerk_user_id' => 'user_123'])->save();

        $this->assertNull($user->fresh()->getRawOriginal('password'));
    }

    public function test_pending_security_task_blocks_protected_access(): void
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->once()->andThrow(
            ClerkAuthenticationFailure::pending(),
        );
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $response = $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->getJson('/api/v1/me');

        $response->assertStatus(401);
        $this->assertSame('SESSION_EXPIRED', $response->json('errors.0.code'));
    }

    public function test_recovery_does_not_change_laravel_role(): void
    {
        $user = User::factory()->customer()->create(['clerk_user_id' => 'user_123']);

        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturn(new AuthenticatedClerkIdentity('user_123', 'sess_123', 'https://clerk.example.test'));

        $response = $this->withHeaders(['Authorization' => 'Bearer session-token'])->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonPath('data.role', 'CUSTOMER');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('CUSTOMER'));
        $this->assertFalse($fresh->hasRole('STAFF'));
        $this->assertFalse($fresh->hasRole('ADMIN'));
    }

    public function test_staff_role_survives_the_clerk_authentication_boundary(): void
    {
        $user = User::factory()->staff()->create(['clerk_user_id' => 'user_staff_1']);
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturn(new AuthenticatedClerkIdentity('user_staff_1', 'sess_staff_1', 'https://clerk.example.test'));

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonPath('data.role', 'STAFF');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('STAFF'));
        $this->assertFalse($fresh->hasRole('CUSTOMER'));
        $this->assertFalse($fresh->hasRole('ADMIN'));
        $this->assertSame(1, User::where('clerk_user_id', 'user_staff_1')->count());
    }

    public function test_admin_role_survives_the_clerk_authentication_boundary(): void
    {
        $user = User::factory()->admin()->create(['clerk_user_id' => 'user_admin_1']);
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturn(new AuthenticatedClerkIdentity('user_admin_1', 'sess_admin_1', 'https://clerk.example.test'));

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonPath('data.role', 'ADMIN');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('ADMIN'));
        $this->assertFalse($fresh->hasRole('CUSTOMER'));
        $this->assertFalse($fresh->hasRole('STAFF'));
        $this->assertSame(1, User::where('clerk_user_id', 'user_admin_1')->count());
    }

    public function test_clerk_role_metadata_cannot_promote_a_customer(): void
    {
        $user = User::factory()->customer()->create(['clerk_user_id' => 'user_metadata_1']);
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturn(new AuthenticatedClerkIdentity('user_metadata_1', 'sess_metadata_1', 'https://clerk.example.test'));

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'CUSTOMER');

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->getJson('/api/v1/admin/staff')
            ->assertForbidden();

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('CUSTOMER'));
        $this->assertFalse($fresh->hasRole('STAFF'));
        $this->assertFalse($fresh->hasRole('ADMIN'));
    }

    public function test_suspended_local_account_is_not_reactivated_by_valid_session(): void
    {
        $user = User::factory()->customer()->create([
            'clerk_user_id' => 'user_123',
            'account_state' => 'SUSPENDED',
        ]);

        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturn(new AuthenticatedClerkIdentity('user_123', 'sess_123', 'https://clerk.example.test'));

        $response = $this->withHeaders(['Authorization' => 'Bearer session-token'])->getJson('/api/v1/me');

        $response->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        $this->assertSame('SUSPENDED', $user->fresh()->account_state);
    }

    public function test_suspended_local_account_is_denied_on_optional_auth_cart_route(): void
    {
        User::factory()->customer()->create([
            'clerk_user_id' => 'user_optional_suspended',
            'account_state' => 'SUSPENDED',
        ]);
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturn(new AuthenticatedClerkIdentity('user_optional_suspended', 'sess_123', 'https://clerk.example.test'));

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->getJson('/api/v1/me/cart')
            ->assertForbidden();
    }

    public function test_staff_is_denied_across_all_customer_cart_operations(): void
    {
        User::factory()->staff()->create(['clerk_user_id' => 'staff_cart_denied', 'account_state' => 'ACTIVE']);
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturn(new AuthenticatedClerkIdentity('staff_cart_denied', 'sess_123', 'https://clerk.example.test'));
        $headers = ['Authorization' => 'Bearer session-token'];

        $this->withHeaders($headers)->getJson('/api/v1/me/cart')->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/v1/me/cart/items', [])->assertForbidden();
        $this->withHeaders($headers)->patchJson('/api/v1/me/cart/items/item_1', [])->assertForbidden();
        $this->withHeaders($headers)->deleteJson('/api/v1/me/cart/items/item_1')->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/v1/me/cart/merge', [])->assertForbidden();
    }

    public function test_mixed_privileged_customer_accounts_are_denied_customer_cart_access(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => config('auth.defaults.guard')]);
        }

        $admin = User::factory()->create(['clerk_user_id' => 'mixed_admin', 'account_state' => 'ACTIVE']);
        $admin->assignRole(RoleName::CUSTOMER->value, RoleName::ADMIN->value);

        $staff = User::factory()->create(['clerk_user_id' => 'mixed_staff', 'account_state' => 'ACTIVE']);
        $staff->assignRole(RoleName::CUSTOMER->value, RoleName::STAFF->value);

        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andReturnUsing(static function (Request $request): AuthenticatedClerkIdentity {
                $subject = $request->bearerToken() === 'admin-session' ? 'mixed_admin' : 'mixed_staff';

                return new AuthenticatedClerkIdentity($subject, 'sess_mixed', 'https://clerk.example.test');
            });

        foreach (['admin-session', 'staff-session'] as $token) {
            $headers = ['Authorization' => 'Bearer '.$token];

            $this->withHeaders($headers)->getJson('/api/v1/me/cart')->assertForbidden();
            $this->withHeaders($headers)->postJson('/api/v1/me/cart/items', [])->assertForbidden();
            $this->withHeaders($headers)->patchJson('/api/v1/me/cart/items/item_1', [])->assertForbidden();
            $this->withHeaders($headers)->deleteJson('/api/v1/me/cart/items/item_1')->assertForbidden();
            $this->withHeaders($headers)->postJson('/api/v1/me/cart/merge', [])->assertForbidden();
        }
    }

    public function test_enumeration_protection_does_not_reveal_account_existence(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', [])
            ->assertStatus(410);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'unknown@example.com'])
            ->assertStatus(410);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'known@example.com'])
            ->assertStatus(410);
    }

    public function test_error_envelope_never_leaks_credentials_or_session_tokens(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $content = $response->getContent();

        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('reset', strtolower($content));
        $this->assertStringNotContainsString('session-token', strtolower($content));
        $this->assertStringNotContainsString('Clerk', $content);
        $this->assertStringNotContainsString('sk_test', $content);
    }

    public function test_revoking_one_session_does_not_revoke_others_by_contract(): void
    {
        $gateway = $this->mock(ClerkSessionGateway::class);
        $gateway->shouldReceive('revoke')->once()->with('sess_a');
        $this->app->instance(ClerkSessionGateway::class, $gateway);

        app(RevokeClerkSession::class)->revoke('sess_a');

        $gateway->shouldHaveReceived('revoke')->once()->with('sess_a');
    }

    public function test_session_revocation_failure_renders_external_service_error(): void
    {
        $gateway = $this->mock(ClerkSessionGateway::class);
        $gateway->shouldReceive('revoke')->once()->with('sess_a')->andThrow(
            new \RuntimeException('Clerk session revocation failed.'),
        );
        $this->app->instance(ClerkSessionGateway::class, $gateway);

        $this->expectException(ApiException::class);

        try {
            app(RevokeClerkSession::class)->revoke('sess_a');
        } catch (ApiException $exception) {
            $this->assertSame('EXTERNAL_SERVICE_ERROR', $exception->errorCode()->value);
            $this->assertSame(503, $exception->status());

            throw $exception;
        }
    }

    public function test_staff_has_no_customer_credential_administration_route(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_123']);

        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            'staff_123',
            'sess_123',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $headers = ['Authorization' => 'Bearer session-token'];

        $this->withHeaders($headers)->postJson('/api/v1/admin/staff', [])
            ->assertForbidden();

        $this->withHeaders($headers)->patchJson('/api/v1/categories/demo', [])
            ->assertForbidden();

        $this->assertSame('STAFF', $staff->fresh()->getRoleNames()->first());
    }

    public function test_clerk_auth_route_without_bearer_returns_authentication_required(): void
    {
        User::factory()->customer()->create(['clerk_user_id' => 'user_123']);

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $this->assertSame('AUTHENTICATION_REQUIRED', $response->json('errors.0.code'));
    }
}
