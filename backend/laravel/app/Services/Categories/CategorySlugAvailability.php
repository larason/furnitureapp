<?php

namespace App\Services\Categories;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Support\ApiErrorCode;
use Illuminate\Database\QueryException;

final class CategorySlugAvailability
{
    public function assertAvailable(string $slug, ?Category $except = null): void
    {
        $query = Category::query()->where('slug', $slug);

        if ($except !== null) {
            $query->whereKeyNot($except->id);
        }

        if ($query->exists()) {
            throw $this->conflict();
        }
    }

    public function throwIfUniqueViolation(QueryException $exception): void
    {
        if (str_contains($exception->getMessage(), 'categories.slug') || str_contains($exception->getMessage(), 'categories_slug_unique')) {
            throw $this->conflict();
        }
    }

    private function conflict(): ApiException
    {
        return new ApiException(ApiErrorCode::CONFLICT, 'A category with this slug already exists.', 409, 'slug');
    }
}
