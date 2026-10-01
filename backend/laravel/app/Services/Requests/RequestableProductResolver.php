<?php

namespace App\Services\Requests;

use App\Exceptions\Api\ApiException;
use App\Exceptions\ProductNotRequestable;
use App\Models\Product;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use App\Support\ProductType;

/**
 * Resolves an optional REQ-001 linked Product.
 *
 * Reuses the catalog's public visibility authority (`Product::public()`:
 * active + published + not soft-deleted + active Category) so the Request API
 * and CAT-001/CAT-002 cannot disagree about visibility. A missing/non-public
 * Product is reported as the canonical public not-found; a publicly visible
 * `IN_STOCK` Product is `PRODUCT_NOT_REQUESTABLE`. ProductStock/Variants are
 * irrelevant to requestability.
 */
final class RequestableProductResolver
{
    public function resolve(?string $productIdentifier): ?Product
    {
        if ($productIdentifier === null) {
            return null;
        }

        $id = ProductIdentifier::decode($productIdentifier);

        $product = $id === null
            ? null
            : Product::query()->public()->whereKey($id)->first();

        if ($product === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested product was not found.', 404, 'product_id');
        }

        if ($product->product_type !== ProductType::MADE_TO_ORDER) {
            throw new ProductNotRequestable;
        }

        return $product;
    }
}
