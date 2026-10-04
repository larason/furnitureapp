<?php

namespace App\Http\Requests;

use App\Services\Products\CreateProductInput;
use App\Support\ProductType;

final class StoreProductRequest extends ProductFormRequest
{
    public function rules(): array
    {
        return $this->productRules(true);
    }

    public function productInput(): CreateProductInput
    {
        $validated = $this->validated();

        return new CreateProductInput(
            name: $validated['name'],
            slug: $validated['slug'],
            description: $validated['description'] ?? null,
            productType: ProductType::from($validated['product_type']),
            priceAmount: $validated['price']['amount'],
            priceCurrency: $validated['price']['currency'],
            categoryIdentifier: $validated['category_id'],
            isActive: $validated['is_active'] ?? true,
            isPublished: $validated['is_published'] ?? true,
        );
    }
}
