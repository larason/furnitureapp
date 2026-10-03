<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\CreateFurnitureRequestRequest;
use App\Http\Requests\ListCustomerHistoryRequest;
use App\Http\Requests\ListOperationalFurnitureRequestsRequest;
use App\Http\Requests\UpdateFurnitureRequestRequest;
use App\Http\Requests\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CustomerFurnitureRequestSummaryResource;
use App\Http\Resources\FurnitureRequestResource;
use App\Http\Resources\OperationalFurnitureRequestResource;
use App\Models\FurnitureRequest;
use App\Models\User;
use App\Services\Attachments\UploadAttachment;
use App\Services\Attachments\UploadCapabilityService;
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
use Illuminate\Support\Facades\DB;

class RequestController extends V1Controller
{
    public function store(
        CreateFurnitureRequestRequest $request,
        CreateFurnitureRequest $creator,
        UploadCapabilityService $capabilities,
    ): JsonResponse {
        $input = $request->normalizedInput();
        $actor = $request->user();
        $command = new CreateFurnitureRequestCommand(
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
        );

        // Capability issuance shares the creation transaction so a failure can
        // never strand a committed request without its upload capability.
        [$furnitureRequest, $uploadToken] = DB::transaction(function () use ($request, $creator, $capabilities, $command): array {
            $furnitureRequest = $creator->create($command);
            $uploadToken = $request->validatedAttachment() === null
                ? $capabilities->issueForRequest((int) $furnitureRequest->getKey())
                : null;

            return [$furnitureRequest, $uploadToken];
        });

        $furnitureRequest->loadMissing(['product', 'attachments']);
        $headers = [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ];

        if ($uploadToken !== null) {
            $headers['X-Upload-Token'] = $uploadToken;
        }

        return (new FurnitureRequestResource($furnitureRequest))
            ->response()
            ->setStatusCode(201)
            ->withHeaders($headers);
    }

    public function meIndex(ListCustomerHistoryRequest $request): JsonResponse
    {
        $user = $this->customerActor($request);
        $pagination = $request->pagination();
        $paginator = FurnitureRequest::query()
            ->where('user_id', $user->getKey())
            ->with(['product', 'attachments'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        $resources = $paginator->getCollection()
            ->map(fn (FurnitureRequest $item): array => (new CustomerFurnitureRequestSummaryResource($item))->resolve($request))
            ->all();

        return $this->customerCollectionResponse($resources, $paginator);
    }

    public function meShow(HttpRequest $request, string $identifier): JsonResponse
    {
        $user = $this->customerActor($request);
        $id = FurnitureRequestIdentifier::decode($identifier);
        $furnitureRequest = $id === null
            ? null
            : FurnitureRequest::query()
                ->whereKey($id)
                ->where('user_id', $user->getKey())
                ->with(['product', 'attachments'])
                ->first();

        if ($furnitureRequest === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested furniture request was not found.', 404);
        }

        return (new FurnitureRequestResource($furnitureRequest))
            ->response()
            ->withHeaders($this->privateHeaders());
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
        $actor = $input->user();
        if (! $actor instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        $updated = $updater->update(
            $furnitureRequest,
            $input->normalizedInput(),
            $actor,
            $input->attributes->get('request_id'),
        );
        $updated->load([
            'product' => static fn ($product) => $product->withTrashed(),
            'attachments',
        ]);

        return $this->resourceResponse($updated, true, $input);
    }

    public function storeAttachment(
        UploadAttachmentRequest $request,
        string $identifier,
        UploadAttachment $uploader,
    ): JsonResponse {
        $furnitureRequest = $this->findAttachmentRequest($identifier);
        $token = $this->authorizeAttachment($request, $furnitureRequest);
        $attachment = $uploader->forRequest($furnitureRequest, $request->validatedAttachment(), $token);

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(201)
            ->withHeaders($this->privateHeaders());
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

    private function findAttachmentRequest(string $identifier): FurnitureRequest
    {
        $id = FurnitureRequestIdentifier::decode($identifier);
        $furnitureRequest = $id === null ? null : FurnitureRequest::query()->whereKey($id)->first();

        if ($furnitureRequest === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested furniture request was not found.', 404);
        }

        return $furnitureRequest;
    }

    private function authorizeAttachment(UploadAttachmentRequest $request, FurnitureRequest $furnitureRequest): ?string
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            $token = $request->header('X-Upload-Token');
            if ($token === null || $token === '') {
                throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication or an upload capability is required.', 401);
            }

            return $token;
        }

        if ($actor->hasRole('CUSTOMER')) {
            if ((int) $furnitureRequest->user_id !== (int) $actor->getKey()) {
                throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested furniture request was not found.', 404);
            }

            return null;
        }

        if (! $actor->checkPermissionTo(PermissionName::REQUESTS_MANAGE->value)) {
            throw new ApiException(ApiErrorCode::FORBIDDEN, 'The authenticated actor cannot upload request attachments.', 403);
        }

        return null;
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

    private function customerActor(HttpRequest $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        if (! $user->hasRole('CUSTOMER')) {
            throw new ApiException(ApiErrorCode::FORBIDDEN, 'The authenticated actor cannot access customer history.', 403);
        }

        return $user;
    }

    /** @param array<int, array<string, mixed>> $resources */
    private function customerCollectionResponse(array $resources, LengthAwarePaginator $paginator): JsonResponse
    {
        $lastPage = max(1, $paginator->lastPage());
        $currentPage = $paginator->currentPage();

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
}
