<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use Tests\TestCase;
use Throwable;

final class LoggingTestBoomException extends \Exception {}

class ApiLoggingTest extends TestCase
{
    private const SECRET = 'SuperSecret123!';

    public function test_request_id_correlates_response_and_logs(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/boom-corr', fn () => throw new LoggingTestBoomException('boom'));

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

        Route::middleware('api')->get('/api/v1/__test__/boom-log', fn () => throw new LoggingTestBoomException('secret stack at /app/Models/Foo.php'));

        $response = $this->getJson('/api/v1/__test__/boom-log');

        $response->assertStatus(500);
        $this->assertSame('INTERNAL_SERVER_ERROR', $response->json('errors.0.code'));
        $this->assertStringNotContainsString('LoggingTestBoomException', $response->getContent());
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
        $this->assertStringNotContainsString('/app/Models', $response->getContent());

        $this->assertCount(1, $captured);
        $this->assertSame('error', $captured[0]->level);
        $this->assertSame(500, $captured[0]->context['status'] ?? null);
        $this->assertStringContainsString('LoggingTestBoomException', $captured[0]->context['exception_class'] ?? '');
        $this->assertArrayNotHasKey('exception', $captured[0]->context);
    }

    public function test_same_class_failures_are_distinguishable_by_redacted_message(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/boom-first', fn () => throw new LoggingTestBoomException('first failure'));
        Route::middleware('api')->get('/api/v1/__test__/boom-second', fn () => throw new LoggingTestBoomException('second failure'));

        $this->getJson('/api/v1/__test__/boom-first')->assertStatus(500);
        $this->getJson('/api/v1/__test__/boom-second')->assertStatus(500);

        $this->assertCount(2, $captured);
        $this->assertSame($captured[0]->context['exception_class'], $captured[1]->context['exception_class']);
        $this->assertSame('first failure', $captured[0]->context['exception_message'] ?? null);
        $this->assertSame('second failure', $captured[1]->context['exception_message'] ?? null);
    }

    public function test_api_server_exception_reaches_registered_error_tracker(): void
    {
        $reported = null;
        $handler = app(ExceptionHandler::class);
        $this->assertInstanceOf(Handler::class, $handler);
        $handler->reportable(function (Throwable $reportedException) use (&$reported): void {
            $reported = $reportedException;
        });
        $exception = new LoggingTestBoomException('tracker-visible failure');
        Route::middleware('api')->get('/api/v1/__test__/tracked-boom', fn () => throw $exception);

        $this->getJson('/api/v1/__test__/tracked-boom')->assertStatus(500);

        $this->assertSame($exception, $reported);
    }

    public function test_exception_message_is_logged_redacted_and_previous_chain_is_excluded(): void
    {
        $events = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$events): void {
            $events[] = $event;
        });
        $testHandler = new TestHandler;
        $logger = Log::getLogger();
        $this->assertInstanceOf(MonologLogger::class, $logger);
        $logger->pushHandler($testHandler);

        Route::middleware('api')->get('/api/v1/__test__/secret-chain', function (): never {
            $previous = new \RuntimeException('Bearer previous-secret-token');
            throw new LoggingTestBoomException('provider body sk_live_current-secret', previous: $previous);
        });

        $this->getJson('/api/v1/__test__/secret-chain')->assertStatus(500);

        $records = $testHandler->getRecords();
        $this->assertNotEmpty($records, 'Expected the sanitized API exception to be written to the log.');
        $this->assertStringContainsString('api.exception', (new LineFormatter)->format($records[0]));

        $apiEvent = collect($events)->firstWhere('message', 'api.exception');
        $this->assertNotNull($apiEvent);
        $this->assertArrayHasKey('exception_message', $apiEvent->context);
        $this->assertStringContainsString('[REDACTED]', (string) $apiEvent->context['exception_message']);
        $this->assertArrayHasKey('exception_trace', $apiEvent->context);

        $this->assertLogExcludes(['previous-secret-token', 'sk_live_current-secret'], $records, $events);
    }

    public function test_sensitive_headers_and_tokens_not_logged(): void
    {
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if ($event->message === 'api.exception') {
                $captured[] = $event;
            }
        });

        Route::middleware('api')->get('/api/v1/__test__/boom-sensitive', fn () => throw new LoggingTestBoomException('boom'));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-access-token',
            'X-Guest-Cart-Id' => 'guest-cart-secret-value',
            'X-Upload-Token' => 'upload-secret-value',
            'X-CSRF-Token' => 'csrf-secret-value',
            'Cookie' => 'session=secret-cookie-value',
        ])->getJson('/api/v1/__test__/boom-sensitive');

        $response->assertStatus(500);

        $this->assertCount(1, $captured);
        $this->assertLogContextHasNoThrowable($captured[0]->context);
        $contextJson = $this->renderLogContext($captured[0]->context);

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

        Route::middleware('api')->post('/api/v1/__test__/boom-body', fn () => throw new LoggingTestBoomException('boom'));

        $response = $this->postJson('/api/v1/__test__/boom-body', [
            'password' => self::SECRET,
            'password_confirmation' => self::SECRET,
            'delivery_address' => '123 Private Street, Dar es Salaam',
            'payment_secret' => 'sk_test_secret',
        ]);

        $response->assertStatus(500);

        $this->assertCount(1, $captured);
        $this->assertLogContextHasNoThrowable($captured[0]->context);
        $contextJson = $this->renderLogContext($captured[0]->context);

        $this->assertStringNotContainsString(self::SECRET, $contextJson);
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

        Route::middleware('api')->get('/api/v1/__test__/boom-inject', fn () => throw new LoggingTestBoomException('boom'));

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
        $this->assertStringNotContainsString("\n", $this->renderLogContext($captured[0]->context));
    }

    public function test_trusted_request_id_is_propagated_when_valid_uuid(): void
    {
        $valid = (string) Str::uuid();

        Route::middleware('api')->get('/api/v1/__test__/boom-trusted', fn () => throw new LoggingTestBoomException('boom'));

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

        Route::middleware('api')->post('/api/v1/__test__/boom-bodies', fn () => throw new LoggingTestBoomException('boom'));

        $body = ['enquiry_message' => 'Free-form private enquiry text that must not appear in logs verbatim'];

        $response = $this->postJson('/api/v1/__test__/boom-bodies', $body);

        $response->assertStatus(500);

        $this->assertCount(1, $captured);
        $this->assertLogContextHasNoThrowable($captured[0]->context);
        $contextJson = $this->renderLogContext($captured[0]->context);

        $this->assertStringNotContainsString('Free-form private enquiry', $contextJson);
    }

    /**
     * Asserts secrets do not reach the formatted log output. Records are run
     * through Monolog's LineFormatter (which renders a context `exception`,
     * including its protected message and previous chain), and event contexts
     * are rendered and inspected for raw Throwable values, since json_encode()
     * would silently omit their private state.
     *
     * @param  array<int, string>  $needles
     * @param  array<int, LogRecord>  $records
     * @param  array<int, MessageLogged>  $events
     */
    private function assertLogExcludes(array $needles, array $records, array $events): void
    {
        $formatted = '';
        foreach ($records as $record) {
            $this->assertLogContextHasNoThrowable($record->context);
            $formatted .= (new LineFormatter)->format($record)."\n";
        }

        $dispatched = '';
        foreach ($events as $event) {
            $this->assertLogContextHasNoThrowable($event->context);
            $dispatched .= $event->message.' '.$this->renderLogContext($event->context)."\n";
        }

        foreach ($needles as $needle) {
            $this->assertStringNotContainsString($needle, $formatted);
            $this->assertStringNotContainsString($needle, $dispatched);
        }
    }

    private function assertLogContextHasNoThrowable(array $context): void
    {
        foreach ($context as $key => $value) {
            $this->assertFalse($value instanceof Throwable, "Log context [{$key}] contains a raw Throwable value.");
            if (is_array($value)) {
                $this->assertLogContextHasNoThrowable($value);
            }
        }
    }

    private function renderLogContext(array $context): string
    {
        $render = function (mixed $value) use (&$render): string {
            if ($value instanceof Throwable) {
                $chain = $value->getMessage();
                for ($previous = $value->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
                    $chain .= ' '.$previous->getMessage();
                }

                return $chain;
            }

            if (is_array($value)) {
                return implode(' ', array_map($render, $value));
            }

            return is_scalar($value) ? (string) $value : '';
        };

        return implode(' ', array_map($render, $context));
    }
}
