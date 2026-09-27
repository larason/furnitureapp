<?php

namespace App\Support;

/**
 * Strict JSON media-type decision for API request bodies. Accepts
 * `application/json` and any `+json` structured syntax suffix. Unlike
 * Laravel's permissive `Request::isJson()`, it rejects `application/jsonp`
 * and other non-JSON content types.
 */
final class JsonMediaType
{
    private const JSON_MEDIA_TYPE = 'application/json';

    private const JSON_SUFFIX = '+json';

    private const TOKEN_PATTERN = '/^[a-z0-9][a-z0-9!#$&^_.+-]{0,126}$/';

    public static function accepts(?string $contentType): bool
    {
        if ($contentType === null) {
            return false;
        }

        $type = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $type === self::JSON_MEDIA_TYPE || self::hasStructuredJsonSuffix($type);
    }

    /**
     * Requires a complete `type/subtype` of valid RFC 6838 restricted-name
     * tokens before the structured `+json` suffix.
     */
    private static function hasStructuredJsonSuffix(string $type): bool
    {
        $parts = explode('/', $type);

        if (count($parts) !== 2) {
            return false;
        }

        [$name, $subtype] = $parts;
        $base = str_ends_with($subtype, self::JSON_SUFFIX)
            ? substr($subtype, 0, -strlen(self::JSON_SUFFIX))
            : '';

        return $name !== ''
            && $base !== ''
            && preg_match(self::TOKEN_PATTERN, $name) === 1
            && preg_match(self::TOKEN_PATTERN, $base) === 1;
    }
}
