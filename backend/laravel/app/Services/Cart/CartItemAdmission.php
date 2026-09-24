<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use App\Support\ProductType;
use App\Support\VariantIdentifier;

/**
 * Shared Cart item admission rule (Phase 6.1/6.3/6.4): resolves and validates
 * the Product/Variant pair that may be placed on a cart line. Under the
 * variant-priced V1 model, a Product without an active Variant is not
 * purchasable rather than "out of stock".
 */
final class CartItemAdmission
{
    /** @return array{0: Product, 1: ProductVariant} */
    public function resolve(string $productIdentifier, ?string $variantIdentifier): array
    {
        $productId = ProductIdentifier::decode($productIdentifier);
        $variantId = $variantIdentifier === null ? null : VariantIdentifier::decode($variantIdentifier);

        if ($variantIdentifier !== null && $variantId === null) {
            throw new ApiException(ApiErrorCode::INVALID_PRODUCT_VARIANT, 'The selected variant is not available for this product.', 422, 'variant_id');
        }

        $product = $this->validatedProduct($this->findProduct($productId));
        $variant = $this->validatedVariant($product, $variantId === null ? null : ProductVariant::find($variantId));

        return [$product, $variant];
    }

    public function findProduct(?int $productId): ?Product
    {
        return $productId === null ? null : Product::withTrashed()->with('category')->withExists('variants')->find($productId);
    }

    public function validatedProduct(?Product $product): Product
    {
        $admissible = $product !== null
            && ! $product->trashed()
            && $product->is_active
            && $product->is_published
            && $product->category->is_active;

        if (! $admissible) {
            throw new ApiException(ApiErrorCode::PRODUCT_UNAVAILABLE, 'The product is not available for purchase.', 422, 'product_id');
        }

        if ($product->product_type === ProductType::MADE_TO_ORDER) {
            throw new ApiException(ApiErrorCode::PRODUCT_NOT_PURCHASABLE, 'The product must be requested instead of purchased.', 422, 'product_id');
        }

        if (! $this->hasPurchasableVariants($product)) {
            throw new ApiException(ApiErrorCode::PRODUCT_NOT_PURCHASABLE, 'The product has no purchasable variant.', 422, 'product_id');
        }

        return $product;
    }

    private function hasPurchasableVariants(Product $product): bool
    {
        return $product->variants_exists !== null
            ? (bool) $product->variants_exists
            : $product->variants()->exists();
    }

    public function validatedVariant(Product $product, ?ProductVariant $variant): ProductVariant
    {
        if ($variant === null || $variant->product_id !== $product->getKey() || ! $variant->is_active) {
            throw new ApiException(ApiErrorCode::INVALID_PRODUCT_VARIANT, 'The selected variant is not available for this product.', 422, 'variant_id');
        }

        return $variant;
    }
}
