<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ip:127.0.0.1');
    }

    public function test_public_read_allows_documented_normal_burst(): void
    {
        Route::middleware('throttle:public-read')->get('/api/v1/__test__/public-read', fn () => response()->json(['ok' => true]));

        foreach (range(1, 5) as $attempt) {
            $this->getJson('/api/v1/__test__/public-read')->assertOk();
        }
    }

    public function test_anonymous_submission_is_limited_by_ip(): void
    {
        Route::middleware('throttle:anonymous-submit')->post('/api/v1/__test__/anonymous-submit', fn () => response()->json(['ok' => true]));

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/v1/__test__/anonymous-submit')->assertOk();
        }

        $response = $this->postJson('/api/v1/__test__/anonymous-submit');

        $response->assertStatus(429);
        $response->assertJsonPath('errors.0.code', 'RATE_LIMITED');
        $this->assertNotNull($response->headers->get('Retry-After'));
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

        foreach (range(1, 5) as $attempt) {
            if ($this->getJson('/api/v1/me', ['Authorization' => 'Bearer first-session'])->status() === 429) {
                $throttled = true;
                break;
            }
        }

        $this->assertTrue($throttled);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer second-session'])->assertOk();
    }
}
