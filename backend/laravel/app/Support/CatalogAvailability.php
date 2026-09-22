<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

final class CatalogAvailability
{
    public const LOW_STOCK_THRESHOLD = 5;

    public static function product(Product $product): array
    {
        if ($product->product_type === ProductType::MADE_TO_ORDER) {
            return ['availability' => 'available', 'stock_indicator' => 'MADE_TO_ORDER'];
        }

        $availableQuantity = $product->summary_available_quantity;
        if ($availableQuantity === null) {
            $availableQuantity = $product->variants
                ->where('is_active', true)
                ->sum(fn (ProductVariant $variant): int => self::variantAvailableQuantity($variant));
        }

        return self::fromQuantity((int) $availableQuantity);
    }

    public static function variant(ProductVariant $variant): array
    {
        if ($variant->product?->product_type === ProductType::MADE_TO_ORDER) {
            return ['availability' => 'available', 'stock_indicator' => 'MADE_TO_ORDER'];
        }

        return self::fromQuantity(self::variantAvailableQuantity($variant));
    }

    private static function variantAvailableQuantity(ProductVariant $variant): int
    {
        return (int) $variant->stocks->sum(
            fn ($stock): int => $stock->quantity - $stock->reserved_quantity
        );
    }

    /**
     * The closed V1 `stock_indicator` enum has no `OUT_OF_STOCK` value, so an
     * exhausted `IN_STOCK` product keeps `IN_STOCK` while `availability`
     * remains the authoritative unavailable signal (Phase 5.7 §33-34).
     *
     * @return array{availability: 'available'|'unavailable', stock_indicator: 'IN_STOCK'|'LOW_STOCK'|'MADE_TO_ORDER'}
     */
    private static function fromQuantity(int $availableQuantity): array
    {
        if ($availableQuantity <= 0) {
            return ['availability' => 'unavailable', 'stock_indicator' => 'IN_STOCK'];
        }

        return [
            'availability' => 'available',
            'stock_indicator' => $availableQuantity <= self::LOW_STOCK_THRESHOLD ? 'LOW_STOCK' : 'IN_STOCK',
        ];
    }
}
