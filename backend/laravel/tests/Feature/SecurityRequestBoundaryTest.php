<?php

namespace Tests\Feature;

use App\Exceptions\Api\ApiException;
use App\Http\Middleware\EnforceApiRequestLimits;
use App\Http\Middleware\ValidateApiRequestLimits;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Cart\GuestCartTransport;
use App\Support\ApiErrorCode;
use Closure;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityRequestBoundaryTest extends TestCase
{
    private const GUEST_MUTATION_URL = '/api/v1/__test__/guest-mutation';

    private const ALLOWED_ORIGIN = 'https://shop.example.test';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('pre-auth:127.0.0.1');
    }

    public function test_oversized_authorization_header_is_rejected_before_routing(): void
    {
        config(['security.max_authorization_header_bytes' => 8]);

        $this->withHeaders(['Authorization' => 'Bearer token-that-is-too-long'])
            ->getJson('/api/v1/me')
            ->assertBadRequest()
            ->assertJsonPath('errors.0.code', 'INVALID_FORMAT');
    }

    public function test_cross_origin_error_response_includes_cors_headers(): void
    {
        config([
            'cors.allowed_origins' => [self::ALLOWED_ORIGIN],
            'cors.supports_credentials' => true,
            'security.max_authorization_header_bytes' => 8,
        ]);

        $response = $this->withHeaders([
            'Origin' => self::ALLOWED_ORIGIN,
            'Authorization' => 'Bearer token-that-is-too-long',
        ])->getJson('/api/v1/me');

        $response->assertBadRequest();
        $this->assertSame(self::ALLOWED_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_cors_preflight_allows_the_idempotency_key_header(): void
    {
        config(['cors.allowed_origins' => [self::ALLOWED_ORIGIN]]);

        $response = $this->call('OPTIONS', '/api/v1/me/cart/merge', server: [
            'HTTP_ORIGIN' => self::ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'idempotency-key',
        ]);

        $response->assertNoContent();
        $this->assertStringContainsString(
            'idempotency-key',
            strtolower((string) $response->headers->get('Access-Control-Allow-Headers')),
        );
    }

    public function test_rate_limited_cross_origin_response_is_readable(): void
    {
        config([
            'cors.allowed_origins' => [self::ALLOWED_ORIGIN],
            'security.pre_auth_requests_per_minute' => 1,
        ]);
        RateLimiter::clear('pre-auth:127.0.0.1');
        Route::middleware('api')->get('/api/v1/__test__/cors-429', fn () => response()->json(['ok' => true]));

        $this->withHeaders(['Origin' => self::ALLOWED_ORIGIN])
            ->getJson('/api/v1/__test__/cors-429')
            ->assertOk();

        $response = $this->withHeaders(['Origin' => self::ALLOWED_ORIGIN])
            ->getJson('/api/v1/__test__/cors-429');

        $response->assertStatus(429);
        $this->assertSame(self::ALLOWED_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_oversized_json_body_is_rejected_before_decoding(): void
    {
        config(['security.max_json_body_bytes' => 10]);
        Route::middleware('api')->post('/api/v1/__test__/body-limit', fn () => response()->json(['ok' => true]));

        $this->postJson('/api/v1/__test__/body-limit', ['value' => str_repeat('x', 50)])
            ->assertStatus(413)
            ->assertJsonPath('errors.0.code', 'REQUEST_TOO_LARGE');
    }

    public function test_vendor_json_media_type_is_size_limited(): void
    {
        config(['security.max_json_body_bytes' => 10]);
        Route::middleware('api')->post('/api/v1/__test__/vendor-body-limit', fn () => response()->json(['ok' => true]));

        $this->call(
            'POST',
            '/api/v1/__test__/vendor-body-limit',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: json_encode(['value' => str_repeat('x', 50)]),
        )->assertStatus(413)->assertJsonPath('errors.0.code', 'REQUEST_TOO_LARGE');
    }

    public function test_vendor_json_media_type_is_validated_as_json(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/vendor-json', fn () => response()->json(['ok' => true]));

        $this->call(
            'POST',
            '/api/v1/__test__/vendor-json',
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            content: '{invalid',
        )->assertBadRequest()->assertJsonPath('errors.0.code', 'INVALID_JSON');
    }

    public function test_form_encoded_api_mutation_is_rejected(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/json-only', fn () => response()->json(['ok' => true]));

        $this->post('/api/v1/__test__/json-only', ['value' => 'x'])
            ->assertStatus(415)
            ->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_multipart_is_allowed_on_contracted_submission_routes(): void
    {
        foreach (['/api/v1/requests', '/api/v1/enquiries'] as $path) {
            $this->withHeaders(['Content-Type' => 'multipart/form-data; boundary=----test'])
                ->post($path, ['name' => 'x'])
                ->assertStatus(501);
        }
    }

    public function test_malformed_multipart_media_type_is_rejected_on_contracted_routes(): void
    {
        $this->withHeaders(['Content-Type' => 'multipart/form-datax'])
            ->post('/api/v1/requests', ['name' => 'x'])
            ->assertStatus(415)
            ->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_multipart_is_rejected_on_json_only_routes(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/multipart-json-only', fn () => response()->json(['ok' => true]));

        $this->withHeaders(['Content-Type' => 'multipart/form-data; boundary=----test'])
            ->post('/api/v1/__test__/multipart-json-only', ['value' => 'x'])
            ->assertStatus(415)
            ->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_chunked_non_json_body_without_content_length_is_rejected(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/chunked-media', fn () => response()->json(['ok' => true]));

        $this->call(
            'POST',
            '/api/v1/__test__/chunked-media',
            server: ['CONTENT_TYPE' => 'text/plain', 'HTTP_TRANSFER_ENCODING' => 'chunked'],
            content: 'plain text body that declares no content length',
        )->assertStatus(415)->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_bodyless_non_json_post_is_allowed(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/bodyless-media', fn () => response()->json(['ok' => true]));

        $this->call('POST', '/api/v1/__test__/bodyless-media', server: ['CONTENT_TYPE' => 'text/plain'])
            ->assertOk();
    }

    public function test_jsonp_media_type_is_not_accepted_as_json(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/jsonp-media', fn () => response()->json(['ok' => true]));

        $this->call(
            'POST',
            '/api/v1/__test__/jsonp-media',
            server: ['CONTENT_TYPE' => 'application/jsonp'],
            content: json_encode(['value' => 'x']),
        )->assertStatus(415)->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_bare_json_suffix_media_type_is_rejected(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/bare-suffix-media', fn () => response()->json(['ok' => true]));

        $this->call(
            'POST',
            '/api/v1/__test__/bare-suffix-media',
            server: ['CONTENT_TYPE' => '+json'],
            content: json_encode(['value' => 'x']),
        )->assertStatus(415)->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_structured_suffix_json_media_type_is_accepted(): void
    {
        Route::middleware('api')->post('/api/v1/__test__/suffix-json-media', fn () => response()->json(['ok' => true]));

        $this->call(
            'POST',
            '/api/v1/__test__/suffix-json-media',
            server: ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode(['value' => 'x']),
        )->assertOk();
    }

    public function test_delete_with_non_json_body_is_rejected(): void
    {
        Route::middleware('api')->delete('/api/v1/__test__/delete-with-body', fn () => response()->noContent());

        $this->call(
            'DELETE',
            '/api/v1/__test__/delete-with-body',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'plain body',
        )->assertStatus(415)->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_bodyless_delete_is_allowed(): void
    {
        Route::middleware('api')->delete('/api/v1/__test__/bodyless-delete', fn () => response()->noContent());

        $this->deleteJson('/api/v1/__test__/bodyless-delete')->assertNoContent();
    }

    public function test_guest_cookie_mutation_requires_an_allowed_origin(): void
    {
        config(['cors.allowed_origins' => [self::ALLOWED_ORIGIN]]);
        Route::middleware(['api', 'guest-cart-mutation'])->post(self::GUEST_MUTATION_URL, fn () => response()->json(['ok' => true]));

        $this->withCredentials()->withUnencryptedCookie(GuestCartTransport::COOKIE, 'credential')
            ->postJson(self::GUEST_MUTATION_URL, [])
            ->assertForbidden();

        $this->withCredentials()->withUnencryptedCookie(GuestCartTransport::COOKIE, 'credential')
            ->withHeaders(['Origin' => 'https://evil.example.test', 'Sec-Fetch-Site' => 'cross-site'])
            ->postJson(self::GUEST_MUTATION_URL, [])
            ->assertForbidden();

        $this->withCredentials()->withUnencryptedCookie(GuestCartTransport::COOKIE, 'credential')
            ->withHeaders(['Origin' => self::ALLOWED_ORIGIN, 'Sec-Fetch-Site' => 'same-site'])
            ->postJson(self::GUEST_MUTATION_URL, [])
            ->assertOk();

        $this->withCredentials()->withUnencryptedCookie(GuestCartTransport::COOKIE, 'credential')
            ->withHeaders(['Origin' => self::ALLOWED_ORIGIN, 'Sec-Fetch-Site' => 'cross-site'])
            ->postJson(self::GUEST_MUTATION_URL, [])
            ->assertOk();
    }

    public function test_authenticated_customer_with_stale_guest_cookie_skips_the_origin_check(): void
    {
        config(['cors.allowed_origins' => [self::ALLOWED_ORIGIN]]);
        Route::middleware(['api', 'guest-cart-mutation'])->post(self::GUEST_MUTATION_URL, fn () => response()->json(['ok' => true]));

        $this->actingAs(User::factory()->make())
            ->withCredentials()
            ->withUnencryptedCookie(GuestCartTransport::COOKIE, 'credential')
            ->postJson(self::GUEST_MUTATION_URL, [])
            ->assertOk();
    }

    public function test_security_headers_are_present_on_success_and_error(): void
    {
        Route::middleware('api')->get('/api/v1/__test__/headers-ok', fn () => response()->json(['ok' => true]));
        Route::middleware('api')->get('/api/v1/__test__/headers-error', fn () => throw new ApiException(ApiErrorCode::INVALID_VALUE, 'No.', 422));

        foreach (['/api/v1/__test__/headers-ok', '/api/v1/__test__/headers-error'] as $path) {
            $response = $this->getJson($path);
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
            $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_pre_authentication_limit_counts_before_post_size_rejection(): void
    {
        config(['security.pre_auth_requests_per_minute' => 5]);
        RateLimiter::clear('pre-auth:127.0.0.1');

        $postSize = new class extends ValidatePostSize
        {
            public function handle($request, Closure $next)
            {
                throw new PostTooLargeException('The POST data is too large.');
            }
        };

        $middleware = new ValidateApiRequestLimits($postSize, app(EnforceApiRequestLimits::class));
        $request = Request::create('/api/v1/anything', 'POST');

        try {
            $middleware->handle($request, fn () => response()->noContent());
            $this->fail('Expected PostTooLargeException.');
        } catch (PostTooLargeException) {
            // expected
        }

        $this->assertSame(1, RateLimiter::attempts('pre-auth:127.0.0.1'));
    }

    public function test_trusted_proxies_are_read_from_the_validated_configuration(): void
    {
        config(['security.trusted_proxies' => ['198.51.100.7']]);
        (new AppServiceProvider($this->app))->boot();

        Route::middleware('api')->get('/api/v1/__test__/client-ip', fn (Request $request) => response()->json(['ip' => $request->ip()]));

        $this->call('GET', '/api/v1/__test__/client-ip', server: [
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ])->assertOk()->assertJsonPath('ip', '203.0.113.9');
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_every_versioned_api_route_has_a_route_limiter(): void
    {
        $unthrottled = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
            ->reject(fn ($route): bool => collect($route->gatherMiddleware())
                ->contains(fn (string $middleware): bool => str_starts_with($middleware, 'throttle:')))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unthrottled);
    }
}
