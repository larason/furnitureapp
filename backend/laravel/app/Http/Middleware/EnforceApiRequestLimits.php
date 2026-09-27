<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use JsonException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class EnforceApiRequestLimits
{
    private const AUTHORIZATION_FIELD = 'Authorization';

    public const JSON_BODY_ATTRIBUTE = 'bounded_json_body';

    private const STREAM_CHUNK_BYTES = 8192;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $key = 'pre-auth:'.$request->ip();
        $limit = (int) config('security.pre_auth_requests_per_minute');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);

        if (app()->isProduction() && ! $request->isSecure()) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'HTTPS is required.', 400);
        }

        $authorization = (string) $request->headers->get('Authorization', '');

        if (strlen($authorization) > (int) config('security.max_authorization_header_bytes')) {
            throw new ApiException(ApiErrorCode::INVALID_FORMAT, 'The Authorization header is too large.', 400, self::AUTHORIZATION_FIELD);
        }

        if ((int) $request->server('CONTENT_LENGTH', 0) > (int) config('security.max_request_body_bytes')) {
            throw new ApiException(ApiErrorCode::REQUEST_TOO_LARGE, 'The request body exceeds the allowed size.', 413);
        }

        $this->captureBoundedJsonBody($request);

        return $next($request);
    }

    private function captureBoundedJsonBody(Request $request): void
    {
        if (! $request->isJson()) {
            return;
        }

        $stream = $request->getContent(true);
        $limit = (int) config('security.max_json_body_bytes');
        $content = '';

        while (! feof($stream) && strlen($content) <= $limit) {
            $chunk = fread($stream, min(self::STREAM_CHUNK_BYTES, ($limit + 1) - strlen($content)));

            if ($chunk === false) {
                throw new ApiException(ApiErrorCode::INVALID_JSON, 'The request body could not be read.', 400);
            }

            $content .= $chunk;
        }

        if (strlen($content) > $limit) {
            throw new ApiException(ApiErrorCode::REQUEST_TOO_LARGE, 'The request body exceeds the allowed size.', 413);
        }

        try {
            $decoded = $content === '' ? [] : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(ApiErrorCode::INVALID_JSON, 'The request body contains invalid JSON.', 400);
        }

        $request->setJson(new InputBag(is_array($decoded) ? $decoded : []));
        $request->attributes->set(self::JSON_BODY_ATTRIBUTE, $content);
    }
}
