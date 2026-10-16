<?php

namespace App\Services\ProductImages;

use App\Exceptions\Api\ApiException;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ApiErrorCode;
use App\Support\ProductImageIdentifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applies a complete, submitted image order. Positions are normalized to the
 * documented 1-based contiguous convention (the value the upload path already
 * assigns as `max + 1`), which also converges legacy zero-based rows. Only
 * `sort_order` is written; ids, storage keys, alt text and primary state are
 * preserved.
 */
final class ReorderProductImages
{
    private const FIRST_POSITION = 1;

    private const FIELD = 'images';

    /**
     * @param  list<string>  $identifiers
     * @return Collection<int, ProductImage>
     */
    public function reorder(Product $product, array $identifiers): Collection
    {
        return DB::transaction(function () use ($product, $identifiers): Collection {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            $images = ProductImage::query()
                ->where('product_id', $lockedProduct->getKey())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $ordered = $this->resolveOrder($images, $identifiers);

            $position = self::FIRST_POSITION;
            foreach ($ordered as $image) {
                if ($image->sort_order !== $position) {
                    ProductImage::query()
                        ->whereKey($image->getKey())
                        ->update(['sort_order' => $position, 'updated_at' => now()]);
                }

                $position++;
            }

            return ProductImage::query()
                ->where('product_id', $lockedProduct->getKey())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * @param  Collection<int, ProductImage>  $images
     * @param  list<string>  $identifiers
     * @return Collection<int, ProductImage>
     */
    private function resolveOrder(Collection $images, array $identifiers): Collection
    {
        $byId = $images->keyBy(fn (ProductImage $image): int => (int) $image->getKey());
        $ordered = collect();

        foreach ($identifiers as $identifier) {
            $id = ProductImageIdentifier::decode($identifier);

            if ($id === null || ! $byId->has($id)) {
                throw new ApiException(
                    ApiErrorCode::INVALID_VALUE,
                    'Every submitted image must belong to this product.',
                    422,
                    self::FIELD
                );
            }

            if ($ordered->contains(fn (ProductImage $image): bool => $image->getKey() === $id)) {
                throw new ApiException(
                    ApiErrorCode::INVALID_VALUE,
                    'A product image may appear only once.',
                    422,
                    self::FIELD
                );
            }

            $ordered->push($byId->get($id));
        }

        if ($ordered->count() !== $images->count()) {
            throw new ApiException(
                ApiErrorCode::INVALID_VALUE,
                'The list must contain every image of the product exactly once.',
                422,
                self::FIELD
            );
        }

        return $ordered;
    }
}
