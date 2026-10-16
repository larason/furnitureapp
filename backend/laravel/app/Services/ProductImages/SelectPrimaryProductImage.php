<?php

namespace App\Services\ProductImages;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;

/**
 * Promotes one image to primary. The existing primary is demoted first because
 * both the model's saving assertion and the `is_primary_guard` unique index
 * reject two primaries for the same product. The product row is locked so
 * concurrent selections serialize instead of racing to that constraint.
 */
final class SelectPrimaryProductImage
{
    public function select(Product $product, ProductImage $image): ProductImage
    {
        return DB::transaction(function () use ($product, $image): ProductImage {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            $target = ProductImage::query()
                ->where('product_id', $lockedProduct->getKey())
                ->whereKey($image->getKey())
                ->firstOrFail();

            if ($target->is_primary) {
                return $target;
            }

            ProductImage::query()
                ->where('product_id', $lockedProduct->getKey())
                ->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => now()]);

            $target->is_primary = true;
            $target->save();

            return $target->refresh();
        });
    }
}
