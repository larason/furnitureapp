<?php

namespace App\Services\ProductImages;

use App\Models\Product;
use App\Models\ProductImage;

/**
 * Alt text is the only mutable image field. The write is scoped to the owning
 * product and touches a single column, so it can never disturb sort order,
 * primary state or the storage reference.
 */
final class UpdateProductImageAltText
{
    public function update(Product $product, ProductImage $image, ?string $altText): ProductImage
    {
        $updated = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->whereKey($image->getKey())
            ->firstOrFail();

        $updated->alt_text = $altText;
        $updated->save();

        return $updated;
    }
}
