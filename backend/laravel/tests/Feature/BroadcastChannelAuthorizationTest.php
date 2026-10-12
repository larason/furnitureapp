<?php

namespace Tests\Feature;

use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\ClerkTokenVerifier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

final class BroadcastChannelAuthorizationTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $environment = [
            'BROADCAST_CONNECTION' => 'reverb',
            'REVERB_APP_ID' => 'test-reverb-app',
            'REVERB_APP_KEY' => 'test-reverb-key',
            'REVERB_APP_SECRET' => 'test-reverb-secret',
        ];
        $previousEnvironment = [];

        foreach ($environment as $key => $value) {
            $previousEnvironment[$key] = getenv($key);
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
        }

        $application = parent::createApplication();

        foreach ($previousEnvironment as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv($key.'='.$value);
                $_ENV[$key] = $value;
            }
        }

        return $application;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_authenticated_recipient_can_authorize_their_own_notification_channel(): void
    {
        $recipient = $this->staff('broadcast_recipient');

        $this->withHeaders($this->authenticateAs($recipient))
            ->postJson('/broadcasting/auth', $this->authorizationPayload($recipient))
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_anonymous_and_cross_user_subscriptions_are_denied(): void
    {
        $recipient = $this->staff('broadcast_recipient');
        $otherStaff = $this->staff('broadcast_other_staff');

        $this->postJson('/broadcasting/auth', $this->authorizationPayload($recipient))
            ->assertUnauthorized();

        $this->withHeaders($this->authenticateAs($otherStaff))
            ->postJson('/broadcasting/auth', $this->authorizationPayload($recipient))
            ->assertForbidden();
    }

    public function test_customer_admin_and_inactive_users_cannot_authorize_another_personal_channel(): void
    {
        $recipient = $this->staff('broadcast_recipient');
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'broadcast_customer']);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'broadcast_admin']);
        $inactiveStaff = User::factory()->staff()->create([
            'account_state' => 'SUSPENDED',
            'clerk_user_id' => 'broadcast_inactive',
        ]);

        $this->withHeaders($this->authenticateAs($customer))
            ->postJson('/broadcasting/auth', $this->authorizationPayload($recipient))
            ->assertForbidden();

        $this->withHeaders($this->authenticateAs($admin))
            ->postJson('/broadcasting/auth', $this->authorizationPayload($recipient))
            ->assertForbidden();

        $this->withHeaders($this->authenticateAs($inactiveStaff))
            ->postJson('/broadcasting/auth', $this->authorizationPayload($inactiveStaff))
            ->assertForbidden();
    }

    public function test_invalid_notification_channel_identifier_is_denied(): void
    {
        $recipient = $this->staff('broadcast_recipient');

        $this->withHeaders($this->authenticateAs($recipient))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1.1',
                'channel_name' => 'private-notifications.invalid',
            ])
            ->assertForbidden();
    }

    public function test_expired_and_invalid_clerk_sessions_are_denied(): void
    {
        $recipient = $this->staff('broadcast_recipient');

        foreach ([ClerkAuthenticationFailure::expired(), ClerkAuthenticationFailure::invalid()] as $failure) {
            $verifier = $this->mock(ClerkTokenVerifier::class);
            $verifier->shouldReceive('verify')->once()->andThrow($failure);
            $this->app->instance(ClerkTokenVerifier::class, $verifier);

            $this->postJson('/broadcasting/auth', $this->authorizationPayload($recipient), [
                'Authorization' => 'Bearer invalid-session-token',
            ])->assertUnauthorized();
        }
    }

    public function test_broadcast_auth_uses_global_request_limits_and_cors_on_errors(): void
    {
        config([
            'cors.allowed_origins' => ['http://localhost:3000'],
            'security.max_authorization_header_bytes' => 8,
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Authorization' => 'Bearer token-that-is-too-long',
        ])->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-notifications.1',
        ]);

        $response->assertBadRequest()
            ->assertJsonPath('errors.0.code', 'INVALID_FORMAT');
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** @return array{socket_id: string, channel_name: string} */
    private function authorizationPayload(User $recipient): array
    {
        return [
            'socket_id' => '1.1',
            'channel_name' => 'private-notifications.'.$recipient->id,
        ];
    }

    private function staff(string $clerkUserId): User
    {
        return User::factory()->staff()->create(['clerk_user_id' => $clerkUserId]);
    }
}
