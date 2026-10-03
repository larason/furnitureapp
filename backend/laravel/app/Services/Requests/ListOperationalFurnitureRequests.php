<?php

namespace App\Services\Requests;

use App\Models\FurnitureRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOperationalFurnitureRequests
{
    /** @return LengthAwarePaginator<int, FurnitureRequest> */
    public function paginate(OperationalFurnitureRequestQuery $filters): LengthAwarePaginator
    {
        $query = FurnitureRequest::query()->with([
            'product' => static fn ($product) => $product->withTrashed(),
            'attachments',
        ]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('furniture_requests.created_at')
            ->orderBy('furniture_requests.id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    private function applyFilters(Builder $query, OperationalFurnitureRequestQuery $filters): void
    {
        if ($filters->search !== null) {
            $this->applySearch($query, $filters->search);
        }

        if ($filters->requestStatus !== null) {
            $query->where('furniture_requests.request_status', $filters->requestStatus->value);
        }

        if ($filters->productId !== null) {
            $query->where('furniture_requests.product_id', $filters->productId);
        }

        if ($filters->createdFrom !== null) {
            $query->where('furniture_requests.created_at', '>=', $filters->createdFrom);
        }

        if ($filters->createdTo !== null) {
            $query->where('furniture_requests.created_at', '<=', $filters->createdTo);
        }
    }

    private function applySearch(Builder $query, string $search): void
    {
        $pattern = '%'.addcslashes($search, '\\%_').'%';

        $query->where(function ($request) use ($pattern): void {
            $request
                ->where('furniture_requests.name', 'like', $pattern)
                ->orWhere('furniture_requests.email', 'like', $pattern)
                ->orWhere('furniture_requests.phone', 'like', $pattern)
                ->orWhere('furniture_requests.request_reference', 'like', $pattern)
                ->orWhereIn('furniture_requests.product_id', $this->matchingProductIds($pattern));
        });
    }

    /** @return Builder<Product> */
    private function matchingProductIds(string $pattern): Builder
    {
        return Product::withTrashed()
            ->select('products.id')
            ->where(function ($product) use ($pattern): void {
                $product
                    ->where('products.name', 'like', $pattern)
                    ->orWhere('products.slug', 'like', $pattern);
            });
    }
}
