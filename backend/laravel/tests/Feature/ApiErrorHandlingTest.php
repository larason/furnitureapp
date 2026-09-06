<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2.7 exception/error-handling foundation tests.
 *
 * Verifies the frozen V1 error envelope across transport, auth, authorization,
 * not-found, validation, rate-limit and unexpected failures.
 */
class ApiErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_api_route_returns_not_found_envelope(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertStatus(404);
        $this->assertErrorEnvelope($response, 'RESOURCE_NOT_FOUND');
    }

    public function test_method_not_allowed_returns_405_envelope(): void
    {
        $response = $this->patchJson('/api/v1/products');

        $response->assertStatus(405);
        $this->assertErrorEnvelope($response, 'METHOD_NOT_ALLOWED');
    }

    public function test_unauthenticated_returns_authentication_required(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
        $this->assertErrorEnvelope($response, 'AUTHENTICATION_REQUIRED');
    }

    public function test_unauthorized_returns_forbidden_envelope(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertStatus(403);
        $this->assertErrorEnvelope($response, 'FORBIDDEN');
    }

    public function test_malformed_json_returns_invalid_json_envelope(): void
    {
        $response = $this->call('POST', '/api/v1/requests', server: ['CONTENT_TYPE' => 'application/json'], content: '{broken');

        $response->assertStatus(400);
        $this->assertErrorEnvelope($response, 'INVALID_JSON');
    }

    public function test_validation_errors_return_multiple_field_errors_with_distinct_codes(): void
    {
        Route::post('/api/v1/__test__/validation', fn () => request()->validate([
            'name' => ['required'],
            'email' => ['email'],
            'delivery_address.city' => ['integer'],
        ]));

        $response = $this->postJson('/api/v1/__test__/validation', [
            'email' => 'not-an-email',
            'delivery_address' => ['city' => 'abc'],
        ]);

        $response->assertStatus(422);
        $json = $response->json();

        $this->assertCount(3, $json['errors']);
        $this->assertSame('name', $json['errors'][0]['field']);
        $this->assertSame('MISSING_REQUIRED_FIELD', $json['errors'][0]['code']);
        $this->assertSame('email', $json['errors'][1]['field']);
        $this->assertSame('INVALID_FORMAT', $json['errors'][1]['code']);
        $this->assertSame('delivery_address.city', $json['errors'][2]['field']);
        $this->assertSame('INVALID_TYPE', $json['errors'][2]['code']);

        $requestIds = array_map(fn (array $error) => $error['request_id'] ?? null, $json['errors']);
        $this->assertSame([null, null, null], $requestIds, 'request_id must not appear inside error objects');
    }

    public function test_unexpected_exception_is_sanitized(): void
    {
        Route::get('/api/v1/__test__/boom', fn () => throw new \RuntimeException('/etc/secrets RuntimeException'));

        $response = $this->getJson('/api/v1/__test__/boom');

        $response->assertStatus(500);
        $this->assertErrorEnvelope($response, 'INTERNAL_SERVER_ERROR');
        $content = $response->getContent();
        $this->assertStringNotContainsString('RuntimeException', $content);
        $this->assertStringNotContainsString('/etc/secrets', $content);
        $this->assertStringNotContainsString('stack', strtolower($content));
    }

    public function test_rate_limited_returns_429_with_retry_after_header(): void
    {
        Route::middleware('throttle:1,1')->get('/api/v1/__test__/throttle', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/v1/__test__/throttle')->assertOk();

        $response = $this->getJson('/api/v1/__test__/throttle');

        $response->assertStatus(429);
        $this->assertErrorEnvelope($response, 'RATE_LIMITED');
        $this->assertNotNull($response->headers->get('Retry-After'));

        $content = $response->getContent();
        $this->assertStringNotContainsString('retry_after_seconds', $content);
    }

    public function test_abort_with_http_status_preserves_that_status(): void
    {
        Route::get('/api/v1/__test__/abort-409', fn () => abort(409));
        Route::get('/api/v1/__test__/abort-503', fn () => abort(503, 'maintenance', ['Retry-After' => '60']));

        $conflict = $this->getJson('/api/v1/__test__/abort-409');

        $conflict->assertStatus(409);
        $this->assertSame('CONFLICT', $conflict->json('errors.0.code'));

        $unavailable = $this->getJson('/api/v1/__test__/abort-503');

        $unavailable->assertStatus(503);
        $this->assertSame('EXTERNAL_SERVICE_ERROR', $unavailable->json('errors.0.code'));
        $this->assertSame('60', $unavailable->headers->get('Retry-After'));
        $this->assertStringNotContainsString('maintenance', $unavailable->getContent());
    }

    public function test_all_api_responses_carry_x_request_id_header(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $this->assertIsString($response->headers->get('X-Request-Id'));
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('meta.request_id'));
    }

    public function test_throttled_request_preserves_consistent_request_id(): void
    {
        Route::middleware('throttle:1,1')->get('/api/v1/__test__/throttle-id', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/v1/__test__/throttle-id')->assertOk();

        $throttled = $this->getJson('/api/v1/__test__/throttle-id');

        $throttled->assertStatus(429);
        $this->assertSame('RATE_LIMITED', $throttled->json('errors.0.code'));
        $this->assertTrue(Str::isUuid($throttled->headers->get('X-Request-Id')));
        $this->assertSame(
            $throttled->headers->get('X-Request-Id'),
            $throttled->json('meta.request_id'),
            'A throttled request must use the same request_id in the response and the X-Request-Id header.',
        );
    }

    private function assertErrorEnvelope($response, string $code): void
    {
        $json = $response->json();

        $this->assertArrayHasKey('errors', $json);
        $this->assertIsArray($json['errors']);
        $this->assertArrayNotHasKey('data', $json);
        $this->assertArrayNotHasKey('error', $json);
        $this->assertArrayNotHasKey('success', $json);
        $this->assertSame($code, $json['errors'][0]['code']);
        $this->assertIsString($json['errors'][0]['message']);
        $this->assertArrayHasKey('request_id', $json['meta']);
        $this->assertTrue(Str::isUuid($json['meta']['request_id']));
    }
}
