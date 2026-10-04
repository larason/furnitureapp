<?php

namespace App\Support;

final class Base36Identifier
{
    private const BASE = 36;

    private const VALUE_PATTERN = '/^[0-9a-z]+$/';

    public static function encode(string $prefix, int $id): string
    {
        return $prefix.base_convert((string) $id, 10, self::BASE);
    }

    public static function decode(string $identifier, string $prefix): ?int
    {
        if (! str_starts_with($identifier, $prefix)) {
            return null;
        }

        $value = substr($identifier, strlen($prefix));

        if ($value === '' || preg_match(self::VALUE_PATTERN, $value) !== 1) {
            return null;
        }

        $decoded = base_convert($value, self::BASE, 10);

        return ctype_digit($decoded) && (int) $decoded > 0 ? (int) $decoded : null;
    }
}
