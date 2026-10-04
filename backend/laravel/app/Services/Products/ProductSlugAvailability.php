<?php

namespace App\Services\Products;

use App\Exceptions\Api\ApiException;
use App\Models\Product;
use App\Support\ApiErrorCode;
use Illuminate\Database\QueryException;

final class ProductSlugAvailability
{
    public function assertAvailable(string $slug, ?Product $except = null): void
    {
        $query = Product::withTrashed()->where('slug', $slug);

        if ($except !== null) {
            $query->whereKeyNot($except->id);
        }

        if ($query->exists()) {
            throw $this->conflict();
        }
    }

    public function throwIfUniqueViolation(QueryException $exception): void
    {
        if ($this->isProductSlugUniqueViolation($exception)) {
            throw $this->conflict();
        }
    }

    private function isProductSlugUniqueViolation(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'products.slug')
            || str_contains($exception->getMessage(), 'products_slug_unique');
    }

    private function conflict(): ApiException
    {
        return new ApiException(ApiErrorCode::CONFLICT, 'A product with this slug already exists.', 409, 'slug');
    }
}
