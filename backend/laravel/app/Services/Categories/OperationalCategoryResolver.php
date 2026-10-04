<?php

namespace App\Services\Categories;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Support\ApiErrorCode;
use App\Support\CategoryIdentifier;

final class OperationalCategoryResolver
{
    public function resolve(string $identifier): Category
    {
        $id = CategoryIdentifier::decode($identifier);
        $category = $id === null
            ? Category::query()->where('slug', $identifier)->first()
            : Category::query()->whereKey($id)->first();

        if ($category === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested category was not found.', 404);
        }

        return $category;
    }
}
