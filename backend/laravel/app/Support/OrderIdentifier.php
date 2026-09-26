<?php

namespace App\Support;

use App\Models\Order;

final class OrderIdentifier
{
    private const PREFIX = 'ord_';

    public static function encode(Order $order): string
    {
        return self::encodeId((int) $order->getKey());
    }

    public static function encodeId(int $id): string
    {
        return self::PREFIX.base_convert((string) $id, 10, 36);
    }

    public static function decode(string $identifier): ?int
    {
        $value = str_starts_with($identifier, self::PREFIX)
            ? substr($identifier, strlen(self::PREFIX))
            : '';
        if ($value === '' || preg_match('/^[0-9a-z]+$/', $value) !== 1) {
            return null;
        }

        $decoded = base_convert($value, 36, 10);

        if (! ctype_digit($decoded) || (int) $decoded <= 0) {
            return null;
        }

        $id = (int) $decoded;

        return self::encodeId($id) === $identifier ? $id : null;
    }
}
