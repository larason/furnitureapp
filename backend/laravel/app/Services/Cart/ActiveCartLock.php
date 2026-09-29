<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Support\ApiErrorCode;

/**
 * Locks a cart row and asserts it is still ACTIVE. Every mutation locks the
 * cart before touching any line so it serialises with CART-005 merge, which
 * locks the source/target carts first and then retires the source; a mutation
 * that loses that race must not silently land on the now-inactive cart.
 */
final class ActiveCartLock
{
    public function acquire(int $cartId): Cart
    {
        $cart = Cart::query()->whereKey($cartId)->lockForUpdate()->first();

        if (! $cart instanceof Cart || ! $cart->isActive()) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The active cart is no longer available.', 409);
        }

        return $cart;
    }
}
