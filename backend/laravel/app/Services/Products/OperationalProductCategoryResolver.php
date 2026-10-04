<?php

namespace App\Services\Products;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Support\ApiErrorCode;
use App\Support\CategoryIdentifier;

final class OperationalProductCategoryResolver
{
    public function resolveActive(string $identifier): Category
    {
        $id = CategoryIdentifier::decode($identifier);
        $category = $id === null
            ? Category::query()->where('slug', $identifier)->where('is_active', true)->first()
            : Category::query()->whereKey($id)->where('is_active', true)->first();

        if ($category === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested category was not found.', 404, 'category_id');
        }

        return $category;
    }
}
