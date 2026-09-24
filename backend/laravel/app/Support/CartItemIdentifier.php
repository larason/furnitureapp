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
        $decoded = null;

        if (str_starts_with($identifier, self::PREFIX)) {
            $value = substr($identifier, strlen(self::PREFIX));

            if ($value !== '' && preg_match('/^[0-9a-z]+$/', $value) === 1) {
                $decoded = self::toInt($value);
            }
        }

        return $decoded;
    }

    private static function toInt(string $value): ?int
    {
        $decoded = 0;

        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $digit = self::digitValue($value[$i]);

            if ($decoded > intdiv(PHP_INT_MAX - $digit, 36)) {
                return null;
            }

            $decoded = $decoded * 36 + $digit;
        }

        return $decoded > 0 ? $decoded : null;
    }

    private static function digitValue(string $character): int
    {
        return $character <= '9' ? (int) $character : ord($character) - 87;
    }
}
