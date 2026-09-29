<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\ClerkTokenVerifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private const ME_URL = '/api/v1/me';

    private const GUEST_CART_CREATE_URL = '/api/v1/__test__/guest-cart-create';

    private const MALFORMED_RATE_LIMIT_URL = '/api/v1/__test__/malformed-rate-limit';

    private const ANONYMOUS_SUBMIT_URL = '/api/v1/__test__/anonymous-submit';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ip:127.0.0.1');
    }

    public function test_public_read_allows_documented_normal_burst(): void
    {
        Route::middleware('throttle:public-read')->get('/api/v1/__test__/public-read', fn () => response()->json(['ok' => true]));

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/v1/__test__/public-read')->assertOk();
        }
    }

    public function test_pre_authentication_limit_stops_clerk_verification(): void
    {
        config(['security.pre_auth_requests_per_minute' => 1]);
        RateLimiter::clear('pre-auth:127.0.0.1');

        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->once()
            ->andThrow(ClerkAuthenticationFailure::invalid());

        $headers = ['Authorization' => 'Bearer invalid'];
        $this->withHeaders($headers)->getJson(self::ME_URL)->assertUnauthorized();
        $this->withHeaders($headers)->getJson(self::ME_URL)
            ->assertStatus(429)
            ->assertJsonPath('errors.0.code', 'RATE_LIMITED');
    }

    public function test_malformed_json_counts_toward_the_pre_authentication_limit(): void
    {
        config(['security.pre_auth_requests_per_minute' => 1]);
        RateLimiter::clear('pre-auth:127.0.0.1');
        Route::middleware('api')->post(self::MALFORMED_RATE_LIMIT_URL, fn () => response()->json(['ok' => true]));

        $server = ['CONTENT_TYPE' => 'application/json'];
        $this->call('POST', self::MALFORMED_RATE_LIMIT_URL, server: $server, content: '{invalid')
            ->assertBadRequest()
            ->assertJsonPath('errors.0.code', 'INVALID_JSON');
        $this->call('POST', self::MALFORMED_RATE_LIMIT_URL, server: $server, content: '{invalid')
            ->assertStatus(429)
            ->assertJsonPath('errors.0.code', 'RATE_LIMITED');
    }

    public function test_anonymous_submission_is_limited_by_ip(): void
    {
        Route::middleware('throttle:anonymous-submit')->post(self::ANONYMOUS_SUBMIT_URL, fn () => response()->json(['ok' => true]));

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::ANONYMOUS_SUBMIT_URL)->assertOk();
        }

        $response = $this->postJson(self::ANONYMOUS_SUBMIT_URL);

        $response->assertStatus(429);
        $response->assertJsonPath('errors.0.code', 'RATE_LIMITED');
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_guest_cart_create_limit_counts_credentialless_creation_attempts(): void
    {
        Route::middleware('throttle:guest-cart-create')
            ->post(self::GUEST_CART_CREATE_URL, fn () => response()->json(['ok' => true]));

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::GUEST_CART_CREATE_URL)->assertOk();
        }

        $this->postJson(self::GUEST_CART_CREATE_URL)
            ->assertStatus(429)
            ->assertJsonPath('errors.0.code', 'RATE_LIMITED');
    }

    public function test_guest_cart_create_limit_counts_invalid_credentials(): void
    {
        Route::middleware('throttle:guest-cart-create')
            ->post(self::GUEST_CART_CREATE_URL, fn () => response()->json(['ok' => true]));

        $headers = ['X-Guest-Cart-Id' => 'not-a-guest-credential'];

        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders($headers)->postJson(self::GUEST_CART_CREATE_URL)->assertOk();
        }

        $this->withHeaders($headers)->postJson(self::GUEST_CART_CREATE_URL)
            ->assertStatus(429)
            ->assertJsonPath('errors.0.code', 'RATE_LIMITED');
    }

    public function test_returning_guest_with_a_valid_credential_is_exempt_from_the_creation_limit(): void
    {
        Route::middleware('throttle:guest-cart-create')
            ->post(self::GUEST_CART_CREATE_URL, fn () => response()->json(['ok' => true]));

        $headers = ['X-Guest-Cart-Id' => (string) Str::uuid()];

        for ($i = 0; $i < 20; $i++) {
            $this->withHeaders($headers)->postJson(self::GUEST_CART_CREATE_URL)->assertOk();
        }
    }

    public function test_authenticated_read_limit_isolated_by_local_user_id(): void
    {
        config(['rate_limits.authenticated_read_per_minute' => 1]);

        $firstUser = User::factory()->customer()->create(['clerk_user_id' => 'user_1001']);
        $secondUser = User::factory()->customer()->create(['clerk_user_id' => 'user_1002']);

        Cache::flush();
        RateLimiter::clear(md5('authenticated-readuser:'.$firstUser->getAuthIdentifier()));
        RateLimiter::clear(md5('authenticated-readuser:'.$secondUser->getAuthIdentifier()));

        $verifier = \Mockery::mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(
            static function (Request $request): AuthenticatedClerkIdentity {
                $clerkUserId = $request->bearerToken() === 'first-session' ? 'user_1001' : 'user_1002';

                return new AuthenticatedClerkIdentity($clerkUserId, null, null);
            },
        );
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $throttled = false;

        for ($i = 0; $i < 5; $i++) {
            if ($this->withHeaders(['Authorization' => 'Bearer first-session'])->getJson(self::ME_URL)->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled);
        $this->withHeaders(['Authorization' => 'Bearer second-session'])->getJson(self::ME_URL)->assertOk();
    }

    public function test_authenticated_write_is_limited_with_retry_after_without_changing_account_state(): void
    {
        config(['rate_limits.authenticated_write_per_minute' => 1]);
        $user = User::factory()->customer()->create(['clerk_user_id' => 'user_write_1']);
        Cache::flush();

        $verifier = \Mockery::mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity('user_write_1', null, null));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $headers = ['Authorization' => 'Bearer write-session'];
        $this->withHeaders($headers)->patchJson(self::ME_URL, ['name' => 'First update'])->assertOk();
        $response = $this->withHeaders($headers)->patchJson(self::ME_URL, ['name' => 'Second update']);

        $response->assertStatus(429)
            ->assertJsonPath('errors.0.code', 'RATE_LIMITED')
            ->assertJsonStructure(['errors', 'meta' => ['request_id']]);
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('Retry-After'));
        $fresh = $user->fresh();
        $this->assertSame('CUSTOMER', $fresh->getRoleNames()->first());
        $this->assertNull($fresh->account_state);
        $this->assertSame('First update', $fresh->name);
    }

    public function test_authenticated_write_quota_isolated_between_users_on_shared_ip(): void
    {
        config(['rate_limits.authenticated_write_per_minute' => 1]);
        $first = User::factory()->customer()->create(['clerk_user_id' => 'user_write_a']);
        $second = User::factory()->customer()->create(['clerk_user_id' => 'user_write_b']);
        Cache::flush();

        $verifier = \Mockery::mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(static function (Request $request): AuthenticatedClerkIdentity {
            return new AuthenticatedClerkIdentity(
                $request->bearerToken() === 'first-write' ? 'user_write_a' : 'user_write_b',
                null,
                null,
            );
        });
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $this->withHeaders(['Authorization' => 'Bearer first-write'])->patchJson(self::ME_URL, ['name' => 'A'])->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer first-write'])->patchJson(self::ME_URL, ['name' => 'A2'])->assertStatus(429);
        $this->withHeaders(['Authorization' => 'Bearer second-write'])->patchJson(self::ME_URL, ['name' => 'B'])->assertOk();

        $this->assertSame('CUSTOMER', $first->fresh()->getRoleNames()->first());
        $this->assertSame('CUSTOMER', $second->fresh()->getRoleNames()->first());
    }
}
