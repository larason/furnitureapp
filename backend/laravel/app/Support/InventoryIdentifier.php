<?php

namespace App\Support;

use App\Models\ProductStock;

final class InventoryIdentifier
{
    private const PREFIX = 'inv_';

    public static function encode(ProductStock $stock): string
    {
        return self::PREFIX.base_convert((string) $stock->getKey(), 10, 36);
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
