<?php

namespace App\Services\Cart;

use App\Http\Resources\CartResource;
use App\Models\Cart;

/**
 * Canonical Cart read projection: eager-loads the relations the resource needs,
 * runs Cart-wide stock revalidation once, and serializes. Shared by the Cart
 * read/mutation responses and the CART-005 merge response so both use the same
 * live pricing/stock projection.
 */
final class CartProjection
{
    public function __construct(private readonly CartStockRevalidator $revalidator) {}

    /** @return array<string, mixed> */
    public function render(Cart $cart): array
    {
        $cart->load([
            'items' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'items.product' => fn ($query) => $query->withTrashed(),
            'items.product.category',
            'items.product.primaryImage',
            'items.product.variants',
            'items.variant',
        ]);

        return (new CartResource($cart, $this->revalidator->revalidate($cart)))->resolve();
    }
}
