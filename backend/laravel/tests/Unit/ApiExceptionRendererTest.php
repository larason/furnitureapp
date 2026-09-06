<?php

namespace Tests\Unit;

use App\Exceptions\Api\ApiException;
use App\Exceptions\Api\ApiExceptionRenderer;
use App\Support\ApiErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Tests\TestCase;

/**
 * Unit tests for the centralized exception-to-contract mapper.
 */
class ApiExceptionRendererTest extends TestCase
{
    private function renderer(): ApiExceptionRenderer
    {
        return app(ApiExceptionRenderer::class);
    }

    private function apiRequest(): Request
    {
        return Request::create('/api/v1/test', 'POST');
    }

    public function test_non_api_requests_fall_through(): void
    {
        $this->assertNull($this->renderer()->render(new \RuntimeException('x'), Request::create('/', 'GET')));
    }

    public function test_api_exception_maps_code_status_field_and_details(): void
    {
        $exception = new ApiException(
            ApiErrorCode::CONFLICT,
            'The operation conflicts with the current state.',
            409,
            'items',
            ['requested' => 5, 'available' => 2],
        );

        $response = $this->renderer()->render($exception, $this->apiRequest());

        $this->assertSame(409, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertSame('CONFLICT', $json['errors'][0]['code']);
        $this->assertSame('items', $json['errors'][0]['field']);
        $this->assertSame(['requested' => 5, 'available' => 2], $json['errors'][0]['details']);
        $this->assertArrayHasKey('request_id', $json['meta']);
    }

    public function test_validation_exception_maps_multiple_nested_errors(): void
    {
        $validator = Validator::make(
            ['delivery_address' => ['city' => 'abc'], 'email' => 'not-an-email'],
            ['name' => ['required'], 'delivery_address.city' => ['integer'], 'email' => ['email']],
        );

        $this->assertTrue($validator->fails());

        $response = $this->renderer()->render(new ValidationException($validator), $this->apiRequest());

        $this->assertSame(422, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertSame([0, 1, 2], array_keys($json['errors']));
        $this->assertSame('MISSING_REQUIRED_FIELD', $json['errors'][0]['code']);
        $this->assertSame('name', $json['errors'][0]['field']);
        $this->assertSame('INVALID_TYPE', $json['errors'][1]['code']);
        $this->assertSame('delivery_address.city', $json['errors'][1]['field']);
        $this->assertSame('INVALID_FORMAT', $json['errors'][2]['code']);
        $this->assertSame('email', $json['errors'][2]['field']);
        $this->assertArrayHasKey('request_id', $json['meta']);
    }

    public function test_framework_exception_status_mappings(): void
    {
        $this->assertMapped('AuthenticationException', new AuthenticationException('x'), 401, 'AUTHENTICATION_REQUIRED');
        $this->assertMapped('AuthorizationException', new AuthorizationException('x'), 403, 'FORBIDDEN');
        $this->assertMapped('AccessDeniedHttpException', new AccessDeniedHttpException('x'), 403, 'FORBIDDEN');
        $this->assertMapped('NotFoundHttpException', new NotFoundHttpException('x'), 404, 'RESOURCE_NOT_FOUND');
        $this->assertMapped('MethodNotAllowedHttpException', new MethodNotAllowedHttpException(['GET'], 'Method not allowed'), 405, 'METHOD_NOT_ALLOWED');
        $this->assertMapped('UnsupportedMediaTypeHttpException', new UnsupportedMediaTypeHttpException('x'), 415, 'UNSUPPORTED_MEDIA_TYPE');
        $this->assertMapped('PostTooLargeException', new PostTooLargeException('x'), 413, 'REQUEST_TOO_LARGE');
    }

    public function test_rate_limited_preserves_retry_after_header(): void
    {
        $response = $this->renderer()->render(new TooManyRequestsHttpException(30, 'Too Many Requests'), $this->apiRequest());

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('RATE_LIMITED', json_decode($response->getContent(), true)['errors'][0]['code']);
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    public function test_generic_http_exceptions_preserve_status_and_use_closest_code(): void
    {
        $this->assertMapped('ConflictHttpException', new HttpException(409, 'Conflict'), 409, 'CONFLICT');
        $this->assertMapped('GoneHttpException', new HttpException(410, 'Gone'), 410, 'RESOURCE_NOT_FOUND');
        $this->assertMapped('HttpException 400', new HttpException(400, 'Bad Request'), 400, 'INVALID_VALUE');
        $this->assertMapped('HttpException 500', new HttpException(500, 'Server Error'), 500, 'INTERNAL_SERVER_ERROR');
    }

    public function test_service_unavailable_preserves_status_retry_after_and_external_code(): void
    {
        $exception = new ServiceUnavailableHttpException(120, 'Down for maintenance');

        $response = $this->renderer()->render($exception, $this->apiRequest());

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('EXTERNAL_SERVICE_ERROR', json_decode($response->getContent(), true)['errors'][0]['code']);
        $this->assertSame('120', $response->headers->get('Retry-After'));
        $this->assertStringNotContainsString('Down for maintenance', $response->getContent());
    }

    public function test_external_service_failures_map_to_502_503_504(): void
    {
        foreach ([502, 503, 504] as $status) {
            $exception = new ApiException(ApiErrorCode::EXTERNAL_SERVICE_ERROR, 'The external service is temporarily unavailable.', $status);

            $response = $this->renderer()->render($exception, $this->apiRequest());

            $json = json_decode($response->getContent(), true);

            $this->assertSame($status, $response->getStatusCode(), "status mismatch for {$status}");
            $this->assertSame('EXTERNAL_SERVICE_ERROR', $json['errors'][0]['code'], "code mismatch for {$status}");
            $this->assertArrayNotHasKey('data', $json);
            $this->assertStringNotContainsString('provider', strtolower($response->getContent()));
        }
    }

    public function test_external_service_failure_preserves_retry_after_header(): void
    {
        $exception = new ApiException(
            ApiErrorCode::EXTERNAL_SERVICE_ERROR,
            'The external service is temporarily unavailable.',
            503,
            headers: ['Retry-After' => '120'],
        );

        $response = $this->renderer()->render($exception, $this->apiRequest());

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('EXTERNAL_SERVICE_ERROR', json_decode($response->getContent(), true)['errors'][0]['code']);
        $this->assertSame('120', $response->headers->get('Retry-After'));
        $this->assertStringNotContainsString('retry_after_seconds', $response->getContent());
    }

    public function test_unexpected_exception_is_sanitized_to_internal_server_error(): void
    {
        $response = $this->renderer()->render(new \RuntimeException('SQLSTATE leak at /app/Models/Foo.php line 12'), $this->apiRequest());

        $this->assertSame(500, $response->getStatusCode());
        $content = $response->getContent();

        $this->assertSame('INTERNAL_SERVER_ERROR', json_decode($content, true)['errors'][0]['code']);
        $this->assertStringNotContainsString('RuntimeException', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('/app/Models', $content);
    }

    private function assertMapped(string $label, \Throwable $e, int $status, string $code): void
    {
        $response = $this->renderer()->render($e, $this->apiRequest());

        $json = json_decode($response->getContent(), true);

        $this->assertSame($status, $response->getStatusCode(), "status mismatch for {$label}");
        $this->assertSame($code, $json['errors'][0]['code'], "code mismatch for {$label}");
        $this->assertArrayNotHasKey('data', $json);
    }
}
