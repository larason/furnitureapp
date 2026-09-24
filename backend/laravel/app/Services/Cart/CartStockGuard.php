<?php

namespace App\Services\Cart;

use App\Models\ProductVariant;

/**
 * Informational stock guard for cart mutations. Delegates the sufficiency
 * decision to {@see CartItemEligibility} (Group E aggregate availability);
 * it never reserves or locks inventory.
 */
final class CartStockGuard
{
    public function assertAvailable(ProductVariant $variant, int $quantity): void
    {
        $reason = CartItemEligibility::stockReason($variant, $quantity);

        if ($reason !== null) {
            throw $reason->toApiException();
        }
    }
}
