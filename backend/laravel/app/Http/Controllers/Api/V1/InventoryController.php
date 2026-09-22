<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\AdjustInventoryRequest;
use App\Http\Requests\InventoryIndexRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Support\ApiErrorCode;
use App\Support\InventoryAdjustmentReason;
use App\Support\InventoryIdentifier;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class InventoryController extends V1Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const NOT_FOUND_MESSAGE = 'The requested inventory was not found.';

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
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, self::NOT_FOUND_MESSAGE, 404);
        }

        return (new InventoryResource($stock))->response()->withHeaders($this->privateHeaders());
    }

    public function adjust(
        AdjustInventoryRequest $request,
        string $inventory,
        InventoryAdjustmentService $adjustments,
    ): JsonResponse {
        $stock = $this->resolveInventory($inventory);

        $data = $adjustments->adjust(
            $stock,
            (int) $request->validated('quantity_delta'),
            InventoryAdjustmentReason::from((string) $request->validated('reason')),
            $this->actor($request),
            $this->idempotencyKey($request),
            (string) $request->attributes->get('request_id'),
        );

        return response()->json(['data' => $data])->withHeaders($this->privateHeaders());
    }

    private function resolveInventory(string $inventory): ProductStock
    {
        $id = InventoryIdentifier::decode($inventory);
        $stock = $id === null ? null : ProductStock::query()->whereKey($id)->first();

        if ($stock === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, self::NOT_FOUND_MESSAGE, 404);
        }

        return $stock;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        return $user;
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            throw new ApiException(ApiErrorCode::MISSING_REQUIRED_FIELD, 'The Idempotency-Key header is required.', 422, 'Idempotency-Key');
        }

        if (! Str::isUuid($key)) {
            throw new ApiException(ApiErrorCode::INVALID_FORMAT, 'The Idempotency-Key header must be a UUID.', 422, 'Idempotency-Key');
        }

        return $key;
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
