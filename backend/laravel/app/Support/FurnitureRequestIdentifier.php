<?php

namespace App\Support;

use App\Models\FurnitureRequest;

/**
 * Opaque public identifier for the Furniture Request resource (`req_...`).
 *
 * Mirrors the existing catalog/identifier convention: the numeric primary key
 * is base36-encoded so raw database ids are never exposed publicly.
 */
final class FurnitureRequestIdentifier
{
    private const PREFIX = 'req_';

    public static function encode(FurnitureRequest $request): string
    {
        return self::encodeId((int) $request->getKey());
    }

    public static function encodeId(int $id): string
    {
        return self::PREFIX.base_convert((string) $id, 10, 36);
    }

    public static function decode(string $identifier): ?int
    {
        if (! str_starts_with($identifier, self::PREFIX)) {
            return null;
        }

        $value = substr($identifier, strlen(self::PREFIX));

        if ($value === '' || preg_match('/^[0-9a-z]+$/', $value) !== 1) {
            return null;
        }

        $decoded = base_convert($value, 36, 10);

        return ctype_digit($decoded) && (int) $decoded > 0 ? (int) $decoded : null;
    }
}
