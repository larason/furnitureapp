<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Support\ConcurrentTransaction;

/**
 * Removes one owned cart line. No catalog/stock validation and no inventory
 * effect — stale lines must always be removable.
 */
final class RemoveCartItem
{
    public function __construct(private readonly ActiveCartLock $cartLock) {}

    public function remove(CartItem $item): void
    {
        ConcurrentTransaction::run(function () use ($item): void {
            $cart = $this->cartLock->acquire($item->cart_id);

            $locked = CartItem::query()
                ->whereKey($item->getKey())
                ->where('cart_id', $cart->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            $locked->delete();
            $cart->touch();
        });
    }
}
