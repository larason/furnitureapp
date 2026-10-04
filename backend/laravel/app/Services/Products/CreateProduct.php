<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreateProduct
{
    public function __construct(
        private readonly OperationalProductCategoryResolver $categories,
        private readonly RequestOnlyProductPublication $publication,
        private readonly ProductSlugAvailability $slugs,
    ) {}

    public function create(CreateProductInput $input): Product
    {
        try {
            return DB::transaction(function () use ($input): Product {
                $this->publication->assertAllowed($input->productType, $input->isPublished);
                $category = $this->categories->resolveActive($input->categoryIdentifier);
                $this->slugs->assertAvailable($input->slug);
                $product = new Product;
                $product->forceFill([
                    'category_id' => $category->id,
                    'name' => $input->name,
                    'slug' => $input->slug,
                    'description' => $input->description,
                    'product_type' => $input->productType,
                    'price_amount' => $input->priceAmount,
                    'price_currency' => $input->priceCurrency,
                    'is_active' => $input->isActive,
                    'is_published' => $input->isPublished,
                ])->save();

                return $product;
            });
        } catch (QueryException $exception) {
            $this->slugs->throwIfUniqueViolation($exception);

            throw $exception;
        }
    }
}
