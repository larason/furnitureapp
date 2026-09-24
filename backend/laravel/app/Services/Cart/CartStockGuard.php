<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Models\ProductVariant;
use App\Support\ApiErrorCode;
use App\Support\CatalogAvailability;

/**
 * Informational stock guard for cart mutations. Reads current Group E
 * aggregate availability only; it never reserves or locks inventory.
 */
final class CartStockGuard
{
    public function assertAvailable(ProductVariant $variant, int $quantity): void
    {
        if (CatalogAvailability::availableQuantity($variant) < $quantity) {
            throw new ApiException(ApiErrorCode::INSUFFICIENT_STOCK, 'The requested quantity is not available.', 422, 'quantity');
        }
    }
}
