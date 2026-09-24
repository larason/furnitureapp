<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;

/**
 * Admission/mutation gate for Cart lines (Phase 6.3–6.6). Resolves opaque
 * identifiers and enforces the shared Product/Variant rules via
 * {@see CartItemEligibility}, mapping internal reasons to Cart errors.
 * Quantity/stock sufficiency is checked separately against the effective
 * quantity, so duplicate-add merging is applied first.
 */
final class CartItemAdmission
{
    /** @return array{0: Product, 1: ProductVariant} */
    public function resolve(string $productIdentifier, ?string $variantIdentifier): array
    {
        $productId = ProductIdentifier::decode($productIdentifier);
        $variantId = $variantIdentifier === null ? null : VariantIdentifier::decode($variantIdentifier);

        if ($variantIdentifier !== null && $variantId === null) {
            throw CartItemInvalidReason::VARIANT_MISSING->toApiException();
        }

        return $this->admissible(
            $this->findProduct($productId),
            $variantId === null ? null : ProductVariant::find($variantId),
        );
    }

    public function findProduct(?int $productId): ?Product
    {
        return $productId === null ? null : Product::withTrashed()->with('category')->withExists('variants')->find($productId);
    }

    /** @return array{0: Product, 1: ProductVariant} */
    public function admissible(?Product $product, ?ProductVariant $variant): array
    {
        if ($product === null) {
            throw CartItemInvalidReason::PRODUCT_MISSING->toApiException();
        }

        $reason = CartItemEligibility::requirementReason($product, $variant);

        if ($reason !== null) {
            throw $reason->toApiException();
        }

        if ($variant === null) {
            throw CartItemInvalidReason::VARIANT_REQUIRED->toApiException();
        }

        return [$product, $variant];
    }
}
