<?php

namespace App\Support;

use App\Models\Order;

final class OrderIdentifier
{
    private const PREFIX = 'ord_';

    public static function encode(Order $order): string
    {
        return self::PREFIX.$order->public_id;
    }

    public static function decode(string $identifier): ?string
    {
        $publicId = str_starts_with($identifier, self::PREFIX)
            ? substr($identifier, strlen(self::PREFIX))
            : null;

        if ($publicId === null || preg_match('/^[0-9a-hjkmnp-tv-z]{26}$/', $publicId) !== 1) {
            return null;
        }

        return $publicId;
    }
}
