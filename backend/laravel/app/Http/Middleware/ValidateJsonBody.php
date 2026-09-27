<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Transport-layer check for malformed JSON on API requests so the contract's
 * 400 INVALID_JSON surfaces before any domain handling.
 */
class ValidateJsonBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasBody = $this->hasBody($request);

        if ($hasBody && ! $request->isJson()) {
            throw new UnsupportedMediaTypeHttpException('API request bodies must use application/json.');
        }

        return $next($request);
    }

    private function hasBody(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)
            && ((int) $request->server('CONTENT_LENGTH', 0) > 0
                || $request->request->all() !== []
                || $request->attributes->get(EnforceApiRequestLimits::JSON_BODY_ATTRIBUTE, '') !== '');
    }
}
