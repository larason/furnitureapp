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
        return Base36Identifier::encode(self::PREFIX, $id);
    }

    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
