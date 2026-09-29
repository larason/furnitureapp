<?php

namespace App\Support;

use App\Models\Cart;

final class CartIdentifier
{
    private const PREFIX = 'cart_';

    private const TRANSIENT_HASH_BYTES = 12;

    public static function encode(Cart $cart): string
    {
        $key = $cart->getKey();

        if ($key !== null) {
            return self::PREFIX.base_convert((string) $key, 10, 36);
        }

        return self::PREFIX.self::transientSuffix((string) $cart->guest_token_digest);
    }

    /**
     * Opaque handle for a not-yet-persisted guest cart, derived from that
     * request's guest token. It never equals a persisted id and is superseded
     * once the cart is created; independent first-time reads yield independent
     * handles.
     */
    private static function transientSuffix(string $digest): string
    {
        return substr(hash('sha256', self::PREFIX.$digest), 0, self::TRANSIENT_HASH_BYTES * 2);
    }
}
