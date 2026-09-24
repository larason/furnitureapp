<?php

namespace App\Support;

use App\Models\CartItem;

final class CartItemIdentifier
{
    private const PREFIX = 'item_';

    public static function encode(CartItem $item): string
    {
        return self::PREFIX.base_convert((string) $item->getKey(), 10, 36);
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
