<?php

namespace App\Support;

use App\Models\Cart;

final class CartIdentifier
{
    private const PREFIX = 'cart_';

    public static function encode(Cart $cart): string
    {
        return self::PREFIX.base_convert((string) ($cart->getKey() ?? 0), 10, 36);
    }
}
