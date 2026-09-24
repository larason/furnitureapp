<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\ApiErrorCode;
use App\Support\ConcurrentTransaction;

/**
 * Updates one owned cart line's quantity after revalidating the current
 * catalog/stock state through the shared admission rules. Quantity only;
 * no reservation, no ProductStock lock.
 */
final class UpdateCartItemQuantity
{
    public function __construct(
        private readonly CartItemAdmission $admission,
        private readonly CartStockGuard $stock,
    ) {}

    public function update(CartItem $item, int $quantity): CartItem
    {
        return ConcurrentTransaction::run(function () use ($item, $quantity): CartItem {
            $locked = CartItem::query()->whereKey($item->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new ApiException(ApiErrorCode::CART_ITEM_NOT_FOUND, 'The requested cart item was not found.', 404);
            }

            [, $variant] = $this->admission->admissible(
                $this->admission->findProduct($locked->product_id),
                $locked->variant_id === null ? null : ProductVariant::find($locked->variant_id),
            );

            $this->stock->assertAvailable($variant, $quantity);

            if ($locked->quantity !== $quantity) {
                $locked->quantity = $quantity;
                $locked->save();
                $locked->cart->touch();
            }

            return $locked;
        });
    }
}
