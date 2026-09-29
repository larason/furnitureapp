<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ApiErrorCode;
use App\Support\ConcurrentTransaction;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a purchasable (Product, Variant) line to the holder's cart. Duplicate
 * additions merge into one line, clamped to CartItem::MAX_QUANTITY; stock is
 * validated informationally only — no reservation, no ProductStock lock.
 */
final class AddCartItem
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly CartStockGuard $stock,
        private readonly ActiveCartLock $cartLock,
    ) {}

    public function add(Cart $cart, Product $product, ProductVariant $variant, int $quantity): CartItem
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->apply($cart, $product, $variant, $quantity);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new ApiException(ApiErrorCode::CONFLICT, 'The cart line could not be updated.', 409);
                }
            }
        }
    }

    private function apply(Cart $cart, Product $product, ProductVariant $variant, int $quantity): CartItem
    {
        return ConcurrentTransaction::run(function () use ($cart, $product, $variant, $quantity): CartItem {
            $lockedCart = $this->cartLock->acquire($cart->getKey());

            $item = CartItem::query()
                ->where('cart_id', $lockedCart->getKey())
                ->where('product_id', $product->getKey())
                ->where('variant_id', $variant->getKey())
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                $this->assertLineCapacity($lockedCart);
            }

            $existingQuantity = $item === null ? 0 : $item->quantity;
            $resulting = min($existingQuantity + $quantity, CartItem::MAX_QUANTITY);

            $this->stock->assertAvailable($variant, $resulting);

            if ($item === null) {
                $item = new CartItem([
                    'cart_id' => $lockedCart->getKey(),
                    'product_id' => $product->getKey(),
                    'variant_id' => $variant->getKey(),
                    'quantity' => $resulting,
                ]);
            } else {
                $item->quantity = $resulting;
            }

            $item->save();
            $lockedCart->touch();

            return $item;
        });
    }

    private function assertLineCapacity(Cart $cart): void
    {
        $lines = CartItem::query()->where('cart_id', $cart->getKey())->count();

        if ($lines >= Cart::MAX_ITEMS) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The cart cannot contain more than '.Cart::MAX_ITEMS.' distinct items.', 422);
        }
    }
}
