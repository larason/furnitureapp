<?php

namespace App\Http\Middleware;

use App\Support\JsonMediaType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Transport-layer check that API request bodies are JSON so the contract's
 * 415 UNSUPPORTED_MEDIA_TYPE and 400 INVALID_JSON surface before any domain
 * handling.
 */
class ValidateJsonBody
{
    private const BODY_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasBody($request) && ! JsonMediaType::accepts($request->headers->get('CONTENT_TYPE'))) {
            throw new UnsupportedMediaTypeHttpException('API request bodies must use application/json.');
        }

        return $next($request);
    }

    private function hasBody(Request $request): bool
    {
        if (! in_array($request->method(), self::BODY_METHODS, true)) {
            return false;
        }

        return (int) $request->server('CONTENT_LENGTH', 0) > 0
            || $request->request->all() !== []
            || $request->attributes->get(EnforceApiRequestLimits::JSON_BODY_ATTRIBUTE, '') !== ''
            || $this->hasUnreadStreamedBody($request);
    }

    /**
     * Detects a body that has no Content-Length and was not parsed as form
     * input (for example a chunked non-JSON request). At most one byte is read
     * and no buffering occurs; a matched body is rejected as unsupported media
     * type immediately afterward.
     */
    private function hasUnreadStreamedBody(Request $request): bool
    {
        $read = fread($request->getContent(true), 1);

        return $read !== false && $read !== '';
    }
}
