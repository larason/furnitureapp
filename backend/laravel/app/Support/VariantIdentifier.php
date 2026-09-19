<?php

namespace App\Support;

use App\Models\ProductVariant;

final class VariantIdentifier
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

    private const PREFIX = 'var_';

    public static function encode(ProductVariant $variant): string
    {
        $value = (int) $variant->getKey();
        $encoded = '';

        do {
            $encoded = self::ALPHABET[$value % 36].$encoded;
            $value = intdiv($value, 36);
        } while ($value > 0);

        return self::PREFIX.$encoded;
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

        $decoded = 0;
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $digit = strpos(self::ALPHABET, $value[$index]);
            if ($digit === false || $decoded > intdiv(PHP_INT_MAX - $digit, 36)) {
                return null;
            }

            $decoded = ($decoded * 36) + $digit;
        }

        return $decoded > 0 ? $decoded : null;
    }
}
