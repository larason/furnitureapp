<?php

namespace App\Services\Cart;

/**
 * Immutable Cart-line evaluation result (Phase 6.6 §43). Only `isPurchasable`,
 * `availability` and `stockIndicator` are contract-visible; `reason` stays
 * internal and is never serialized.
 */
final readonly class CartItemValidationResult
{
    /**
     * @param  'available'|'unavailable'  $availability
     * @param  'IN_STOCK'|'LOW_STOCK'|'MADE_TO_ORDER'  $stockIndicator
     */
    public function __construct(
        public bool $isPurchasable,
        public string $availability,
        public string $stockIndicator,
        public ?CartItemInvalidReason $reason = null,
    ) {}
}
