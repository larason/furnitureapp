<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifies the cross-origin contract between the Next.js website and the
 * Laravel API: explicit allowed origins, working preflight for the Clerk
 * bearer + JSON/multipart submissions, and a deny-by-default posture for
 * every other origin.
 */
class CorsConfigurationTest extends TestCase
{
    private const ALLOWED_ORIGIN = 'https://shop.example.test';

    private const SECOND_ALLOWED_ORIGIN = 'https://store.example.test';

    private const UNKNOWN_ORIGIN = 'https://evil.example.test';

    private const PROBE_URL = '/api/v1/__test__/cors-probe';

    protected function setUp(): void
    {
        parent::setUp();
        // Two explicit origins mirror the real local configuration and keep the
        // CORS service on its dynamic origin-matching path. With a single origin
        // fruitcake/php-cors emits that origin unconditionally, which is still
        // browser-safe (the origin must match) but does not exercise denial.
        config(['cors.allowed_origins' => [self::ALLOWED_ORIGIN, self::SECOND_ALLOWED_ORIGIN]]);
        Route::middleware('api')->get(self::PROBE_URL, fn () => response()->json(['ok' => true]));
    }

    public function test_preflight_from_allowed_origin_permits_post_with_authorization_and_content_type(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/requests', server: [
            'HTTP_ORIGIN' => self::ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $response->assertNoContent();
        $this->assertSame(self::ALLOWED_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));

        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('content-type', $allowedHeaders);

        $this->assertStringContainsString('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertNotNull($response->headers->get('Access-Control-Max-Age'));
    }

    public function test_actual_request_from_allowed_origin_is_granted_the_origin(): void
    {
        $this->withHeaders(['Origin' => self::ALLOWED_ORIGIN])
            ->getJson(self::PROBE_URL)
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
    }

    public function test_actual_request_from_unknown_origin_is_not_granted_cors_access(): void
    {
        $response = $this->withHeaders(['Origin' => self::UNKNOWN_ORIGIN])
            ->getJson(self::PROBE_URL)
            ->assertOk();

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_preflight_from_unknown_origin_is_not_granted_cors_access(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/requests', server: [
            'HTTP_ORIGIN' => self::UNKNOWN_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_error_response_keeps_cors_headers_for_allowed_origin(): void
    {
        $response = $this->withHeaders(['Origin' => self::ALLOWED_ORIGIN])->getJson('/api/v1/me');

        $response->assertUnauthorized()->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
        $this->assertSame(self::ALLOWED_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cors_does_not_bypass_authentication(): void
    {
        $this->withHeaders(['Origin' => self::ALLOWED_ORIGIN])
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_bearer_authorization_and_json_content_type_are_allowed_request_headers(): void
    {
        $headers = array_map('strtolower', config('cors.allowed_headers'));

        $this->assertContains('authorization', $headers);
        $this->assertContains('content-type', $headers);
    }

    public function test_origins_are_explicit_without_patterns_or_wildcards(): void
    {
        $this->assertContains('api/*', config('cors.paths'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertSame([], config('cors.allowed_origins_patterns'));
    }

    public function test_second_allowed_origin_is_also_granted(): void
    {
        $this->withHeaders(['Origin' => self::SECOND_ALLOWED_ORIGIN])
            ->getJson(self::PROBE_URL)
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', self::SECOND_ALLOWED_ORIGIN);
    }
}
