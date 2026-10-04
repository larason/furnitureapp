<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Support\ProductType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class UpdateProduct
{
    public function __construct(
        private readonly OperationalProductCategoryResolver $categories,
        private readonly ProductSlugAvailability $slugs,
    ) {}

    public function update(Product $product, UpdateProductInput $input): Product
    {
        try {
            return DB::transaction(function () use ($product, $input): Product {
                $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $attributes = $this->attributes($input);

                if (isset($attributes['slug'])) {
                    $this->slugs->assertAvailable($attributes['slug'], $lockedProduct);
                }

                if ($input->has('category_id')) {
                    $attributes['category_id'] = $this->categories->resolveActive($input->attributes['category_id'])->id;
                }

                $lockedProduct->forceFill($attributes);

                if ($lockedProduct->isDirty()) {
                    $lockedProduct->save();
                }

                return $lockedProduct;
            });
        } catch (QueryException $exception) {
            $this->slugs->throwIfUniqueViolation($exception);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function attributes(UpdateProductInput $input): array
    {
        $attributes = $input->attributes;

        if ($input->has('product_type')) {
            $attributes['product_type'] = ProductType::from($attributes['product_type']);
        }

        if ($input->has('price')) {
            $attributes['price_amount'] = $attributes['price']['amount'];
            $attributes['price_currency'] = $attributes['price']['currency'];
            unset($attributes['price']);
        }

        unset($attributes['category_id']);

        return $attributes;
    }
}
