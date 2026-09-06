<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transport-layer check for malformed JSON on API requests so the contract's
 * 400 INVALID_JSON surfaces before any domain handling.
 */
class ValidateJsonBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isJsonPayload($request)) {
            try {
                json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ApiException(ApiErrorCode::INVALID_JSON, 'The request body contains invalid JSON.', 400);
            }
        }

        return $next($request);
    }

    private function isJsonPayload(Request $request): bool
    {
        $contentType = strtolower((string) $request->headers->get('CONTENT_TYPE', ''));

        return $request->getContent() !== '' && str_contains($contentType, 'application/json');
    }
}
