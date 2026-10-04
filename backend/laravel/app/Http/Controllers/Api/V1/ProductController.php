<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\OperationalProductIndexRequest;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\OperationalProductResource;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductSummaryResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Queries\ProductCatalogQuery;
use App\Services\Products\CreateProduct;
use App\Services\Products\UpdateProduct;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->public()
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

        $resolved->variants->each(fn ($variant) => $variant->setRelation('product', $resolved));

        return (new ProductDetailResource($resolved))->response()->withHeaders($this->publicCacheHeaders());
    }

    public function store(StoreProductRequest $request, CreateProduct $creator): JsonResponse
    {
        $product = $this->loadOperationalProduct($creator->create($request->productInput()));

        return (new OperationalProductResource($product, $this->canViewInventory($request)))
            ->response()
            ->setStatusCode(201)
            ->withHeaders($this->privateHeaders());
    }

    public function update(UpdateProductRequest $request, string $product, UpdateProduct $updater): JsonResponse
    {
        $updated = $updater->update($this->resolveOperationalProduct($product), $request->productInput());

        return (new OperationalProductResource($this->loadOperationalProduct($updated), $this->canViewInventory($request)))
            ->response()
            ->withHeaders($this->privateHeaders());
    }

    public function storeImage(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function adminIndex(OperationalProductIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $this->operationalProducts()->paginate($perPage, ['*'], 'page', $page);
        $products = $paginator->getCollection()->map(fn (Product $product) => $this->prepareOperationalProduct($product));
        $lastPage = max(1, $paginator->lastPage());
        $currentPage = min($paginator->currentPage(), $lastPage);

        return response()->json([
            'data' => $products->map(
                fn (Product $product): array => (new OperationalProductResource($product, $this->canViewInventory($request)))->resolve($request),
            )->all(),
            'meta' => ['pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $paginator->total(),
                'last_page' => $lastPage,
                'has_next' => $currentPage < $lastPage,
                'has_previous' => $currentPage > 1,
            ]],
        ])->withHeaders($this->privateHeaders());
    }

    public function adminShow(string $product, Request $request): JsonResponse
    {
        return (new OperationalProductResource($this->loadOperationalProduct($this->resolveOperationalProduct($product)), $this->canViewInventory($request)))
            ->response()
            ->withHeaders($this->privateHeaders());
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

    /** @return Builder<Product> */
    private function operationalProducts(): Builder
    {
        return Product::query()
            ->with($this->operationalRelations())
            ->orderByDesc('created_at')
            ->orderBy('id');
    }

    private function resolveOperationalProduct(string $identifier): Product
    {
        $id = ProductIdentifier::decode($identifier);
        $product = $id === null
            ? $this->operationalProducts()->where('slug', $identifier)->first()
            : $this->operationalProducts()->whereKey($id)->first();

        if ($product === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested product was not found.', 404);
        }

        return $this->prepareOperationalProduct($product);
    }

    private function loadOperationalProduct(Product $product): Product
    {
        return $this->prepareOperationalProduct($product->load($this->operationalRelations()));
    }

    private function prepareOperationalProduct(Product $product): Product
    {
        $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));

        return $product;
    }

    private function operationalRelations(): array
    {
        return [
            'category:id,name,slug,description',
            'primaryImage:id,product_id,file_path,alt_text,sort_order,is_primary',
            'images:id,product_id,file_path,alt_text,sort_order,is_primary',
            'variants' => fn ($variant) => $variant->with('stocks:id,product_variant_id,quantity,reserved_quantity'),
        ];
    }

    private function canViewInventory(Request $request): bool
    {
        return $request->user()?->checkPermissionTo('inventory.view') ?? false;
    }

    private function privateHeaders(): array
    {
        return ['Cache-Control' => 'private, no-store', 'Vary' => 'Authorization'];
    }

    private function resolvePublicProduct(string $identifier): Product
    {
        $query = Product::query()->public();
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
