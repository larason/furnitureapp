<?php

namespace Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiLoggingTest extends TestCase
{
    public function test_request_id_correlates_response_and_logs(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/boom-corr', fn () => throw new \RuntimeException('boom'));

        $response = $this->getJson('/api/v1/__test__/boom-corr');

        $response->assertStatus(500);
        $requestId = $response->json('meta.request_id');

        $this->assertTrue(Str::isUuid($requestId));
        $this->assertSame($requestId, $response->headers->get('X-Request-Id'));
        $this->assertCount(1, $captured);
        $this->assertSame($requestId, $captured[0]->context['request_id'] ?? null);
        $this->assertSame(500, $captured[0]->context['status'] ?? null);
    }

    public function test_multiple_logs_within_one_request_share_same_request_id(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if (str_starts_with($event->message, 'api.test.')) {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/multi-log', function () {
            Log::info('api.test.first', ['request_id' => request()->attributes->get('request_id')]);
            Log::info('api.test.second', ['request_id' => request()->attributes->get('request_id')]);

            return response()->json(['ok' => true]);
        });

        $response = $this->getJson('/api/v1/__test__/multi-log');

        $response->assertOk();
        $requestId = $response->headers->get('X-Request-Id');

        $this->assertCount(2, $captured);
        $this->assertSame($requestId, $captured[0]->context['request_id'] ?? null);
        $this->assertSame($requestId, $captured[1]->context['request_id'] ?? null);
        $this->assertSame($captured[0]->context['request_id'], $captured[1]->context['request_id']);
    }

    public function test_unexpected_exception_logged_with_request_id_and_sanitized_response(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/boom-log', fn () => throw new \RuntimeException('secret stack at /app/Models/Foo.php'));

        $response = $this->getJson('/api/v1/__test__/boom-log');

        $response->assertStatus(500);
        $this->assertSame('INTERNAL_SERVER_ERROR', $response->json('errors.0.code'));
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
        $this->assertStringNotContainsString('/app/Models', $response->getContent());

        $this->assertCount(1, $captured);
        $this->assertSame('error', $captured[0]->level);
        $this->assertSame(500, $captured[0]->context['status'] ?? null);
        $this->assertStringContainsString('RuntimeException', $captured[0]->context['exception_class'] ?? '');
    }

    public function test_sensitive_headers_and_tokens_not_logged(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/boom-sensitive', fn () => throw new \RuntimeException('boom'));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-access-token',
            'X-Guest-Cart-Id' => 'guest-cart-secret-value',
            'X-Upload-Token' => 'upload-secret-value',
            'X-CSRF-Token' => 'csrf-secret-value',
            'Cookie' => 'session=secret-cookie-value',
        ])->getJson('/api/v1/__test__/boom-sensitive');

        $response->assertStatus(500);

        $this->assertCount(1, $captured);
        $contextJson = json_encode($captured[0]->context);

        $this->assertStringNotContainsString('secret-access-token', $contextJson);
        $this->assertStringNotContainsString('guest-cart-secret-value', $contextJson);
        $this->assertStringNotContainsString('upload-secret-value', $contextJson);
        $this->assertStringNotContainsString('csrf-secret-value', $contextJson);
        $this->assertStringNotContainsString('secret-cookie', $contextJson);
    }

    public function test_password_and_private_address_not_logged(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->post('/api/v1/__test__/boom-body', fn () => throw new \RuntimeException('boom'));

        $response = $this->postJson('/api/v1/__test__/boom-body', [
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
            'delivery_address' => '123 Private Street, Dar es Salaam',
            'payment_secret' => 'sk_test_secret',
        ]);

        $response->assertStatus(500);

        $this->assertCount(1, $captured);
        $contextJson = json_encode($captured[0]->context);

        $this->assertStringNotContainsString('SuperSecret123!', $contextJson);
        $this->assertStringNotContainsString('123 Private Street', $contextJson);
        $this->assertStringNotContainsString('sk_test_secret', $contextJson);
    }

    public function test_validation_failure_not_logged_as_error(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->level === 'error') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->post('/api/v1/__test__/validation-log', fn () => request()->validate([
            'name' => ['required'],
        ]));

        $response = $this->postJson('/api/v1/__test__/validation-log', []);

        $response->assertStatus(422);
        $this->assertEmpty($captured, 'Expected client validation failures must not create ERROR-level log noise');
    }

    public function test_log_injection_via_request_id_is_sanitized(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/boom-inject', fn () => throw new \RuntimeException('boom'));

        $malicious = "evil\nFAKE LOG LINE\r\n[2025-01-01] fake.critical: injected";

        $response = $this->withHeaders(['X-Request-Id' => $malicious])->getJson('/api/v1/__test__/boom-inject');

        $response->assertStatus(500);
        $requestId = $response->json('meta.request_id');

        $this->assertTrue(Str::isUuid($requestId));
        $this->assertStringNotContainsString("\n", $requestId);
        $this->assertStringNotContainsString($malicious, $requestId);

        $this->assertCount(1, $captured);
        $loggedId = $captured[0]->context['request_id'] ?? '';

        $this->assertTrue(Str::isUuid($loggedId));
        $this->assertSame($requestId, $loggedId);
        $this->assertStringNotContainsString("\n", json_encode($captured[0]->context));
    }

    public function test_trusted_request_id_is_propagated_when_valid_uuid(): void
    {
        $valid = (string) Str::uuid();

        Route::middleware('api')->get('/api/v1/__test__/boom-trusted', fn () => throw new \RuntimeException('boom'));

        $response = $this->withHeaders(['X-Request-Id' => $valid])->getJson('/api/v1/__test__/boom-trusted');

        $this->assertSame($valid, $response->json('meta.request_id'));
        $this->assertSame($valid, $response->headers->get('X-Request-Id'));
    }

    public function test_request_and_response_bodies_not_logged_generically(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->post('/api/v1/__test__/boom-bodies', fn () => throw new \RuntimeException('boom'));

        $body = ['enquiry_message' => 'Free-form private enquiry text that must not appear in logs verbatim'];

        $response = $this->postJson('/api/v1/__test__/boom-bodies', $body);

        $response->assertStatus(500);

        $this->assertCount(1, $captured);
        $contextJson = json_encode($captured[0]->context);

        $this->assertStringNotContainsString('Free-form private enquiry', $contextJson);
    }
}
