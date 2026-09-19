<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Resources\CategoryDetailResource;
use App\Http\Resources\CategorySummaryResource;
use App\Models\Category;
use App\Support\ApiErrorCode;
use App\Support\CategoryIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryController extends V1Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    private const ROOT_SLUG = 'furnitures-root';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        $rootId = Category::query()->where('slug', self::ROOT_SLUG)->value('id');
        $paginator = Category::query()
            ->when($rootId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($rootId !== null, fn ($query) => $query->where('parent_id', $rootId))
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->collectionResponse($paginator, $page, $perPage);
    }

    public function show(string $category): JsonResponse
    {
        $query = Category::query()->where('is_active', true);
        $decodedId = CategoryIdentifier::decode($category);
        $resolved = $decodedId === null
            ? $query->where('slug', $category)->first()
            : $query->whereKey($decodedId)->first();

        if ($resolved === null || $resolved->slug === self::ROOT_SLUG || $resolved->parent_id === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested category was not found.', 404);
        }

        return (new CategoryDetailResource($resolved))->response()
            ->header('Cache-Control', 'public, max-age=300, s-maxage=600')
            ->header('CDN-Cache-Control', 'public, max-age=600');
    }

    public function store(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function update(): JsonResponse
    {
        return $this->notImplemented();
    }

    private function collectionResponse(LengthAwarePaginator $paginator, int $page, int $perPage): JsonResponse
    {
        $lastPage = max(1, $paginator->lastPage());
        $currentPage = min($page, $lastPage);

        return response()->json([
            'data' => CategorySummaryResource::collection($paginator->getCollection())->resolve(),
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
        ])->withHeaders([
            'Cache-Control' => 'public, max-age=300, s-maxage=600',
            'CDN-Cache-Control' => 'public, max-age=600',
        ]);
    }
}
