<?php

namespace App\Services\Enquiries;

use App\Exceptions\Api\ApiException;
use App\Models\Product;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;

/**
 * Resolves an optional ENQ-001 linked Product for enquiry context.
 *
 * Unlike Furniture Requests, any currently public Product type is accepted
 * (`IN_STOCK` or `MADE_TO_ORDER`); only visibility matters. It reuses the
 * catalog public visibility authority (`Product::public()`), so hidden/missing
 * Products are masked as the canonical public not-found.
 */
final class PublicEnquiryProductResolver
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

        return $product;
    }
}
