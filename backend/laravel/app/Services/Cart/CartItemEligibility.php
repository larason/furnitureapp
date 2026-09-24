<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CatalogAvailability;
use App\Support\ProductType;

/**
 * Single Cart-domain eligibility authority (Phase 6.6). One evaluation feeds
 * admission/mutation (via mapped errors) and projection (via the result), so
 * Product/Variant/quantity rules cannot drift between Cart workflows.
 *
 * Stock sufficiency is informational only: it reads Group E aggregate
 * availability and never reserves or locks inventory.
 */
final class CartItemEligibility
{
    public static function evaluate(?Product $product, ?ProductVariant $variant, int $quantity): CartItemValidationResult
    {
        $reason = self::lineReason($product, $variant, $quantity);
        $bucket = self::availabilityBucket($product, $variant);

        return new CartItemValidationResult(
            isPurchasable: $reason === null,
            availability: $bucket['availability'],
            stockIndicator: $bucket['stock_indicator'],
            reason: $reason,
        );
    }

    public static function productReason(Product $product): ?CartItemInvalidReason
    {
        return match (true) {
            $product->trashed() => CartItemInvalidReason::PRODUCT_DELETED,
            ! $product->is_active => CartItemInvalidReason::PRODUCT_INACTIVE,
            ! $product->is_published => CartItemInvalidReason::PRODUCT_UNPUBLISHED,
            ! $product->category->is_active => CartItemInvalidReason::CATEGORY_INACTIVE,
            $product->product_type === ProductType::MADE_TO_ORDER => CartItemInvalidReason::MADE_TO_ORDER,
            default => null,
        };
    }

    public static function missingVariantReason(Product $product): CartItemInvalidReason
    {
        return self::hasVariants($product)
            ? CartItemInvalidReason::VARIANT_REQUIRED
            : CartItemInvalidReason::PRODUCT_WITHOUT_VARIANT;
    }

    /**
     * Product/Variant rules only (no stock). Admission and mutation evaluate
     * this half before the transaction; {@see evaluate()} composes it with
     * {@see stockReason()} for projection.
     */
    public static function requirementReason(Product $product, ?ProductVariant $variant): ?CartItemInvalidReason
    {
        return self::productReason($product)
            ?? ($variant === null ? self::missingVariantReason($product) : self::variantReason($product, $variant));
    }

    public static function variantReason(Product $product, ProductVariant $variant): ?CartItemInvalidReason
    {
        return match (true) {
            $variant->product_id !== $product->getKey() => CartItemInvalidReason::VARIANT_WRONG_PARENT,
            ! $variant->is_active => CartItemInvalidReason::VARIANT_INACTIVE,
            default => null,
        };
    }

    public static function stockReason(ProductVariant $variant, int $quantity): ?CartItemInvalidReason
    {
        $available = CatalogAvailability::availableQuantity($variant);

        return match (true) {
            $available <= 0 => CartItemInvalidReason::OUT_OF_STOCK,
            $available < $quantity => CartItemInvalidReason::INSUFFICIENT_FOR_CART_QUANTITY,
            default => null,
        };
    }

    public static function isPubliclyVisible(Product $product): bool
    {
        return ! $product->trashed()
            && $product->is_active
            && $product->is_published
            && $product->category->is_active;
    }

    private static function lineReason(?Product $product, ?ProductVariant $variant, int $quantity): ?CartItemInvalidReason
    {
        if ($product === null) {
            return CartItemInvalidReason::PRODUCT_MISSING;
        }

        return self::requirementReason($product, $variant)
            ?? ($variant === null ? null : self::stockReason($variant, $quantity));
    }

    /** @return array{availability: string, stock_indicator: string} */
    private static function availabilityBucket(?Product $product, ?ProductVariant $variant): array
    {
        if ($product !== null && self::isPubliclyVisible($product) && $variant !== null && $variant->is_active && $variant->product_id === $product->getKey()) {
            return CatalogAvailability::variant($variant);
        }

        return [
            'availability' => 'unavailable',
            'stock_indicator' => $product === null ? 'IN_STOCK' : CatalogAvailability::product($product)['stock_indicator'],
        ];
    }

    private static function hasVariants(Product $product): bool
    {
        if ($product->relationLoaded('variants')) {
            return $product->variants->isNotEmpty();
        }

        return $product->variants_exists !== null
            ? (bool) $product->variants_exists
            : $product->variants()->exists();
    }
}
