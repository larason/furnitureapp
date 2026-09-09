<?php

namespace App\Exceptions\Api;

use App\Support\ApiErrorCode;
use App\Support\ApiErrorResponse;
use App\Support\ApiLogContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Throwable;

/**
 * Centralized mapping of failures to the frozen V1 error envelope for
 * `/api/v1`. Translates failures only; it never decides business policy.
 * Non-API requests fall through to Laravel's default rendering.
 */
class ApiExceptionRenderer
{
    public function __construct(private readonly ApiErrorResponse $response) {}

    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return $this->map($e, $request);
    }

    private function map(Throwable $e, Request $request): ?JsonResponse
    {
        return match (true) {
            $e instanceof ApiException => $this->apiExceptionResponse($e, $request),
            $e instanceof HttpResponseException => null,
            $e instanceof ValidationException => $this->validation($request, $e),
            $e instanceof AuthenticationException => $this->response->error(401, ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', $request),
            $e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException => $this->response->error(403, ApiErrorCode::FORBIDDEN, 'You are not authorized to perform this action.', $request),
            $e instanceof NotFoundHttpException => $this->response->error(404, ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested resource was not found.', $request),
            $e instanceof MethodNotAllowedHttpException => $this->response->error(405, ApiErrorCode::METHOD_NOT_ALLOWED, 'The HTTP method is not supported for this resource.', $request),
            $e instanceof UnsupportedMediaTypeHttpException => $this->response->error(415, ApiErrorCode::UNSUPPORTED_MEDIA_TYPE, 'The request Content-Type is not supported.', $request),
            $e instanceof PostTooLargeException => $this->response->error(413, ApiErrorCode::REQUEST_TOO_LARGE, 'The request body exceeds the allowed size.', $request),
            $e instanceof TooManyRequestsHttpException => $this->response->error(429, ApiErrorCode::RATE_LIMITED, 'Too many requests.', $request, headers: $e->getHeaders()),
            $e instanceof HttpExceptionInterface => $this->httpExceptionResponse($e, $request),
            default => $this->genericError($request, $e),
        };
    }

    private function apiExceptionResponse(ApiException $e, Request $request): JsonResponse
    {
        $this->logIfServerError($request, $e, $e->errorCode(), $e->status());

        return $this->response->error($e->status(), $e->errorCode(), $e->getMessage(), $request, $e->field(), $e->details(), $e->headers());
    }

    private function httpExceptionResponse(HttpExceptionInterface $e, Request $request): JsonResponse
    {
        $status = $e->getStatusCode();

        $this->logIfServerError($request, $e, $this->httpExceptionCode($status), $status);

        return $this->response->error(
            $status,
            $this->httpExceptionCode($status),
            $this->httpExceptionMessage($status),
            $request,
            headers: $e->getHeaders(),
        );
    }

    private function genericError(Request $request, Throwable $e): JsonResponse
    {
        $this->log($request, $e, ApiErrorCode::INTERNAL_SERVER_ERROR, 500);

        return $this->response->error(500, ApiErrorCode::INTERNAL_SERVER_ERROR, 'An unexpected error occurred.', $request);
    }

    private function log(Request $request, Throwable $exception, ApiErrorCode $code, int $status): void
    {
        try {
            Log::error('api.exception', ApiLogContext::forException($request, $exception, $code, $status));
        } catch (Throwable $e) {
            error_log('api.exception logging failed: '.$e->getMessage());
        }
    }

    private function logIfServerError(Request $request, Throwable $exception, ApiErrorCode $code, int $status): void
    {
        if ($status >= 500) {
            $this->log($request, $exception, $code, $status);
        }
    }

    private function httpExceptionCode(int $status): ApiErrorCode
    {
        return match (true) {
            $status === 409 => ApiErrorCode::CONFLICT,
            $status >= 502 && $status <= 504 => ApiErrorCode::EXTERNAL_SERVICE_ERROR,
            $status >= 500 => ApiErrorCode::INTERNAL_SERVER_ERROR,
            $status === 410 => ApiErrorCode::RESOURCE_NOT_FOUND,
            default => ApiErrorCode::INVALID_VALUE,
        };
    }

    private function httpExceptionMessage(int $status): string
    {
        return $status >= 500
            ? 'The service is temporarily unavailable.'
            : 'The request could not be completed.';
    }

    private function validation(Request $request, ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $errors[] = $this->response->errorObject(
                $this->validationCode($e, $field),
                $messages[0],
                $field,
            );
        }

        return $this->response->make(422, $errors, $request);
    }

    private function validationCode(ValidationException $e, string $field): ApiErrorCode
    {
        $failed = $e->validator->failed();

        foreach (array_keys($failed[$field] ?? []) as $rule) {
            if (in_array($rule, $this->missingRules(), true)) {
                return ApiErrorCode::MISSING_REQUIRED_FIELD;
            }

            if (in_array($rule, $this->formatRules(), true)) {
                return ApiErrorCode::INVALID_FORMAT;
            }

            if (in_array($rule, $this->typeRules(), true)) {
                return ApiErrorCode::INVALID_TYPE;
            }
        }

        return ApiErrorCode::INVALID_VALUE;
    }

    private function missingRules(): array
    {
        return ['Accepted', 'ActiveUrl', 'Required', 'RequiredIf', 'RequiredUnless', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll', 'RequiredWithCount'];
    }

    private function formatRules(): array
    {
        return ['After', 'AfterOrEqual', 'Before', 'BeforeOrEqual', 'Date', 'DateFormat', 'DateEquals', 'Email', 'Image', 'Ip', 'Ipv4', 'Ipv6', 'Json', 'MacAddress', 'Mimetypes', 'Mimes', 'Url', 'Uuid', 'Timezone'];
    }

    private function typeRules(): array
    {
        return ['Array', 'Boolean', 'Integer', 'Object', 'String'];
    }
}
