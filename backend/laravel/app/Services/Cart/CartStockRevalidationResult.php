<?php

namespace App\Services\Cart;

use App\Models\CartItem;

/**
 * Cart-wide live stock revalidation outcome (Phase 6.7 §55). Holds the
 * per-line {@see CartItemValidationResult} keyed by internal CartItem id.
 * Internal only — never serialized, never persisted.
 */
final readonly class CartStockRevalidationResult
{
    /** @param  array<int, CartItemValidationResult>  $resultsByItemId */
    public function __construct(private array $resultsByItemId) {}

    public function forItem(CartItem $item): ?CartItemValidationResult
    {
        return $this->resultsByItemId[$item->getKey()] ?? null;
    }
}
