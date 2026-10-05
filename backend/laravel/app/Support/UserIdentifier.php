<?php

namespace App\Support;

final class UserIdentifier
{
    private const PREFIX = 'user_';

    public static function encodeId(?int $id): ?string
    {
        return $id === null ? null : self::PREFIX.base_convert((string) $id, 10, 36);
    }

    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
