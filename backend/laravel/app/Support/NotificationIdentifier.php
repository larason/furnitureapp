<?php

namespace App\Support;

final class NotificationIdentifier
{
    private const PREFIX = 'not_';

    public static function encodeId(int $id): string
    {
        return Base36Identifier::encode(self::PREFIX, $id);
    }

    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
