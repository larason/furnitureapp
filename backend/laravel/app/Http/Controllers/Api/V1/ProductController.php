<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductSummaryResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Queries\ProductCatalogQuery;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
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
        $currentPage = min($paginator->currentPage(), $lastPage);

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

    public function show(string $product, ProductCatalogQuery $catalog): JsonResponse
    {
        $query = Product::query()
            ->select('products.*')
            ->where('is_active', true)
            ->whereHas('category', fn ($category) => $category->where('is_active', true))
            ->with([
                'category:id,name,slug,description',
                'primaryImage:id,product_id,file_path,alt_text,sort_order,is_primary',
                'images:id,product_id,file_path,alt_text,sort_order,is_primary',
                'variants' => fn ($variant) => $variant->where('is_active', true)->orderBy('display_order')->orderBy('id')->with('stocks:id,product_variant_id,quantity,reserved_quantity'),
            ]);
        $catalog->addSummaryAggregates($query);
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

    public function indexVariants(string $product): JsonResponse
    {
        $resolvedProduct = $this->resolvePublicProduct($product);
        $variants = $resolvedProduct->variants()
            ->where('is_active', true)
            ->with('stocks:id,product_variant_id,quantity,reserved_quantity')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $variants->each(fn ($variant) => $variant->setRelation('product', $resolvedProduct));

        return response()->json([
            'data' => ProductVariantResource::collection($variants)->resolve(),
        ])->withHeaders($this->publicCacheHeaders());
    }

    public function storeVariant(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function showVariant(string $product, string $variant): JsonResponse
    {
        $resolvedProduct = $this->resolvePublicProduct($product);
        $variantId = VariantIdentifier::decode($variant);
        $resolvedVariant = $variantId === null ? null : $resolvedProduct->variants()
            ->where('is_active', true)
            ->whereKey($variantId)
            ->with('stocks:id,product_variant_id,quantity,reserved_quantity')
            ->first();

        if ($resolvedVariant === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested variant was not found.', 404);
        }

        $resolvedVariant->setRelation('product', $resolvedProduct);

        return (new ProductVariantResource($resolvedVariant))->response()->withHeaders($this->publicCacheHeaders());
    }

    private function publicCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'public, max-age=300, s-maxage=600',
            'CDN-Cache-Control' => 'public, max-age=600',
        ];
    }

    private function resolvePublicProduct(string $identifier): Product
    {
        $query = Product::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($category) => $category->where('is_active', true));
        $decodedId = ProductIdentifier::decode($identifier);
        $product = $decodedId === null
            ? $query->where('slug', $identifier)->first()
            : $query->whereKey($decodedId)->first();

        if ($product === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested product was not found.', 404);
        }

        return $product;
    }
}
