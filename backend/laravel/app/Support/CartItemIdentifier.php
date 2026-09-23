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
}
