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
    public function remove(CartItem $item): void
    {
        ConcurrentTransaction::run(function () use ($item): void {
            $locked = CartItem::query()->whereKey($item->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $cart = $locked->cart;
            $locked->delete();
            $cart->touch();
        });
    }
}
