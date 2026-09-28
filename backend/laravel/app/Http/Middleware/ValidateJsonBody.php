<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use App\Support\JsonMediaType;
use Closure;
use Illuminate\Http\Request;
use JsonException;
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

    /**
     * Contracted endpoints that accept `multipart/form-data` attachments
     * (`api-contract.md` §13.16, REQ-001/ENQ-001 inline, REQ-007/ENQ-007).
     */
    private const MULTIPART_ROUTES = [
        'api.requests.store',
        'api.enquiries.store',
        'api.requests.attachments.store',
        'api.enquiries.attachments.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasBody($request)
            && ! JsonMediaType::accepts($request->headers->get('CONTENT_TYPE'))
            && ! $this->isContractedMultipartUpload($request)) {
            throw new UnsupportedMediaTypeHttpException('API request bodies must use application/json.');
        }

        $this->assertDecodableJson($request);

        return $next($request);
    }

    /**
     * Rejects malformed JSON for accepted `application/json` (and `+json`)
     * bodies before they reach the application. The bounded body captured by
     * `EnforceApiRequestLimits` is reused when present so non-seekable request
     * streams are not read a second time.
     */
    private function assertDecodableJson(Request $request): void
    {
        if (! JsonMediaType::accepts($request->headers->get('CONTENT_TYPE'))) {
            return;
        }

        $content = $request->attributes->has(EnforceApiRequestLimits::JSON_BODY_ATTRIBUTE)
            ? (string) $request->attributes->get(EnforceApiRequestLimits::JSON_BODY_ATTRIBUTE)
            : (string) $request->getContent();

        if ($content === '') {
            return;
        }

        try {
            json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(ApiErrorCode::INVALID_JSON, 'The request body contains invalid JSON.', 400);
        }
    }

    private function isContractedMultipartUpload(Request $request): bool
    {
        $contentType = (string) $request->headers->get('CONTENT_TYPE');
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        if ($mediaType !== 'multipart/form-data') {
            return false;
        }

        return in_array($request->route()?->getName(), self::MULTIPART_ROUTES, true);
    }

    private function hasBody(Request $request): bool
    {
        if (! in_array($request->method(), self::BODY_METHODS, true)) {
            return false;
        }

        return (int) $request->server('CONTENT_LENGTH', 0) > 0
            || $request->request->all() !== []
            || $request->files->all() !== []
            || $request->attributes->get(EnforceApiRequestLimits::JSON_BODY_ATTRIBUTE, '') !== ''
            || $this->hasUnreadStreamedBody($request);
    }

    /**
     * Detects a body that has no Content-Length and was not parsed as form
     * input (for example a chunked non-JSON request). Seekable streams are
     * probed with a single byte that is then rewound. Non-seekable streams
     * cannot be rewound, so they are read through the request content cache,
     * which leaves the full body available to downstream readers.
     */
    private function hasUnreadStreamedBody(Request $request): bool
    {
        $stream = $request->getContent(true);

        if (! stream_get_meta_data($stream)['seekable']) {
            return $request->getContent() !== '';
        }

        $read = fread($stream, 1);
        rewind($stream);

        return $read !== false && $read !== '';
    }
}
