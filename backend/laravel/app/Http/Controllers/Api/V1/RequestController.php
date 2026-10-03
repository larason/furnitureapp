<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\CreateFurnitureRequestRequest;
use App\Http\Requests\ListOperationalFurnitureRequestsRequest;
use App\Http\Requests\UpdateFurnitureRequestRequest;
use App\Http\Resources\FurnitureRequestResource;
use App\Http\Resources\OperationalFurnitureRequestResource;
use App\Models\FurnitureRequest;
use App\Models\User;
use App\Services\Requests\CreateFurnitureRequest;
use App\Services\Requests\CreateFurnitureRequestCommand;
use App\Services\Requests\ListOperationalFurnitureRequests;
use App\Services\Requests\UpdateFurnitureRequestOperationalFields;
use App\Support\ApiErrorCode;
use App\Support\FurnitureRequestIdentifier;
use App\Support\PermissionName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class RequestController extends V1Controller
{
    public function store(CreateFurnitureRequestRequest $request, CreateFurnitureRequest $creator): JsonResponse
    {
        $input = $request->normalizedInput();
        $actor = $request->user();

        $furnitureRequest = $creator->create(new CreateFurnitureRequestCommand(
            actor: $actor instanceof User ? $actor : null,
            productId: $input->productId,
            quantity: $input->quantity,
            name: $input->name,
            phone: $input->phone,
            email: $input->email,
            dimensions: $input->dimensions,
            material: $input->material,
            color: $input->color,
            notes: $input->notes,
            attachment: $request->validatedAttachment(),
        ));

        $furnitureRequest->loadMissing(['product', 'attachments']);

        return (new FurnitureRequestResource($furnitureRequest))
            ->response()
            ->setStatusCode(201)
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Vary' => 'Authorization',
            ]);
    }

    public function meIndex(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function meShow(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function index(
        ListOperationalFurnitureRequestsRequest $request,
        ListOperationalFurnitureRequests $requests,
    ): JsonResponse {
        $paginator = $requests->paginate($request->normalizedQuery());
        $includeInternalNotes = $this->canManage($request);

        return $this->collectionResponse($paginator, $includeInternalNotes, $request);
    }

    public function show(HttpRequest $httpRequest, string $request): JsonResponse
    {
        $furnitureRequest = $this->findOperationalRequest($request);
        $furnitureRequest->load([
            'product' => static fn ($product) => $product->withTrashed(),
            'attachments',
        ]);

        return $this->resourceResponse($furnitureRequest, $this->canManage($httpRequest), $httpRequest);
    }

    public function update(
        UpdateFurnitureRequestRequest $input,
        string $request,
        UpdateFurnitureRequestOperationalFields $updater,
    ): JsonResponse {
        $furnitureRequest = $this->findOperationalRequest($request);
        $updated = $updater->update($furnitureRequest, $input->normalizedInput());
        $updated->load([
            'product' => static fn ($product) => $product->withTrashed(),
            'attachments',
        ]);

        return $this->resourceResponse($updated, true, $input);
    }

    public function storeAttachment(): JsonResponse
    {
        return $this->notImplemented();
    }

    private function findOperationalRequest(string $identifier): FurnitureRequest
    {
        $id = FurnitureRequestIdentifier::decode($identifier);
        $furnitureRequest = $id === null
            ? null
            : FurnitureRequest::query()->whereKey($id)->first();

        if ($furnitureRequest === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested furniture request was not found.', 404);
        }

        return $furnitureRequest;
    }

    private function canManage(HttpRequest $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->checkPermissionTo(PermissionName::REQUESTS_MANAGE->value);
    }

    private function resourceResponse(FurnitureRequest $furnitureRequest, bool $includeInternalNotes, HttpRequest $request): JsonResponse
    {
        return (new OperationalFurnitureRequestResource($furnitureRequest, $includeInternalNotes))
            ->response()
            ->withHeaders($this->privateHeaders());
    }

    /** @param LengthAwarePaginator<int, FurnitureRequest> $paginator */
    private function collectionResponse(LengthAwarePaginator $paginator, bool $includeInternalNotes, HttpRequest $request): JsonResponse
    {
        $lastPage = max(1, $paginator->lastPage());
        $currentPage = $paginator->currentPage();
        $resources = $paginator->getCollection()
            ->map(fn (FurnitureRequest $furnitureRequest): array => (new OperationalFurnitureRequestResource($furnitureRequest, $includeInternalNotes))->resolve($request))
            ->all();

        return response()->json([
            'data' => $resources,
            'meta' => [
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $lastPage,
                    'has_next' => $currentPage < $lastPage,
                    'has_previous' => $currentPage > 1,
                ],
            ],
        ])->withHeaders($this->privateHeaders());
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ];
    }
}
