<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use App\Queries\ProductCatalogQuery;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use Illuminate\Http\JsonResponse;

class ProductController extends V1Controller
{
    public function index(ProductIndexRequest $request, ProductCatalogQuery $catalog): JsonResponse
    {
        $validated = $request->validated();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $catalog->build($validated)->paginate($perPage, ['*'], 'page', $page);
        $lastPage = max(1, $paginator->lastPage());
        $currentPage = $paginator->currentPage();

        return response()->json([
            'data' => ProductSummaryResource::collection($paginator->getCollection())->resolve(),
            'meta' => ['pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $paginator->total(),
                'last_page' => $lastPage,
                'has_next' => $currentPage < $lastPage,
                'has_previous' => $currentPage > 1,
            ]],
        ])->withHeaders($this->publicCacheHeaders());
    }

    public function show(string $product): JsonResponse
    {
        $query = Product::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($category) => $category->where('is_active', true))
            ->with([
                'category:id,name,slug',
                'primaryImage:id,product_id,file_path,alt_text,sort_order,is_primary',
                'images:id,product_id,file_path,alt_text,sort_order,is_primary',
                'variants' => fn ($variant) => $variant->where('is_active', true)->orderBy('display_order')->orderBy('id')->with('stocks:id,product_variant_id,quantity,reserved_quantity'),
            ]);
        $decodedId = ProductIdentifier::decode($product);
        $resolved = $decodedId === null
            ? $query->where('slug', $product)->first()
            : $query->whereKey($decodedId)->first();

        if ($resolved === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested product was not found.', 404);
        }

        return (new ProductDetailResource($resolved))->response()->withHeaders($this->publicCacheHeaders());
    }

    public function store(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function update(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function storeImage(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function adminIndex(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function adminShow(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function indexVariants(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function storeVariant(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function showVariant(): JsonResponse
    {
        return $this->notImplemented();
    }

    private function publicCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'public, max-age=300, s-maxage=600',
            'CDN-Cache-Control' => 'public, max-age=600',
        ];
    }
}
