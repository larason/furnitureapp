<?php

namespace App\Support;

use App\Exceptions\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mandatory `Idempotency-Key` header validation for sensitive mutations.
 * Missing/malformed keys are rejected before any business effect.
 */
final class IdempotencyKeyHeader
{
    public const HEADER = 'Idempotency-Key';

    public static function require(Request $request): string
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || trim($key) === '') {
            throw new ApiException(ApiErrorCode::MISSING_REQUIRED_FIELD, 'The Idempotency-Key header is required.', 422, self::HEADER);
        }

        if (! Str::isUuid($key)) {
            throw new ApiException(ApiErrorCode::INVALID_FORMAT, 'The Idempotency-Key header must be a UUID.', 422, self::HEADER);
        }

        return $key;
    }
}
