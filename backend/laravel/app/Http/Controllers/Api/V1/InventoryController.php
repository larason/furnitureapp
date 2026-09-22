<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\InventoryIndexRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Product;
use App\Models\ProductStock;
use App\Support\ApiErrorCode;
use App\Support\InventoryIdentifier;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryController extends V1Controller
{
    private const DEFAULT_PER_PAGE = 20;

    public function index(InventoryIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $query = ProductStock::query()->with('productVariant');
        $this->applyFilters($query, $validated);

        $paginator = $query
            ->orderByDesc('product_stocks.updated_at')
            ->orderBy('product_stocks.id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->collectionResponse($paginator, $page, $perPage);
    }

    public function show(string $inventory): JsonResponse
    {
        $id = InventoryIdentifier::decode($inventory);
        $stock = $id === null ? null : ProductStock::query()
            ->with('productVariant')
            ->whereKey($id)
            ->first();

        if ($stock === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested inventory was not found.', 404);
        }

        return (new InventoryResource($stock))->response()->withHeaders($this->privateHeaders());
    }

    public function adjust(): JsonResponse
    {
        return $this->notImplemented();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($this->filled($filters, 'product')) {
            $this->applyProductFilter($query, (string) $filters['product']);
        }

        if ($this->filled($filters, 'variant')) {
            $variantId = VariantIdentifier::decode((string) $filters['variant']);
            $variantId === null
                ? $query->whereRaw('1 = 0')
                : $query->where('product_stocks.product_variant_id', $variantId);
        }

        if ($this->filled($filters, 'warehouse_location')) {
            $query->where('product_stocks.warehouse_location', $filters['warehouse_location']);
        }
    }

    private function applyProductFilter(Builder $query, string $identifier): void
    {
        $productId = ProductIdentifier::decode($identifier);

        if ($productId === null && ctype_digit($identifier)) {
            $productId = (int) $identifier;
        }

        if ($productId === null) {
            $productId = Product::withTrashed()->where('slug', $identifier)->value('id');
        }

        $productId === null
            ? $query->whereRaw('1 = 0')
            : $query->whereHas('productVariant', fn (Builder $variant) => $variant->where('product_id', $productId));
    }

    private function filled(array $filters, string $key): bool
    {
        return isset($filters[$key]) && $filters[$key] !== '';
    }

    private function collectionResponse(LengthAwarePaginator $paginator, int $page, int $perPage): JsonResponse
    {
        $lastPage = max(1, $paginator->lastPage());
        $currentPage = min($page, $lastPage);

        return response()->json([
            'data' => InventoryResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => $paginator->total(),
                    'last_page' => $lastPage,
                    'has_next' => $currentPage < $lastPage,
                    'has_previous' => $currentPage > 1,
                ],
            ],
        ])->withHeaders($this->privateHeaders());
    }

    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ];
    }
}
