<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\CloseEnquiryRequest;
use App\Http\Requests\CreateEnquiryRequest;
use App\Http\Requests\ListCustomerHistoryRequest;
use App\Http\Requests\ListOperationalEnquiriesRequest;
use App\Http\Requests\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CustomerEnquirySummaryResource;
use App\Http\Resources\EnquiryResource;
use App\Http\Resources\OperationalEnquiryResource;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\Attachments\UploadAttachment;
use App\Services\Attachments\UploadCapabilityService;
use App\Services\Enquiries\CloseEnquiry;
use App\Services\Enquiries\CreateEnquiry;
use App\Services\Enquiries\CreateEnquiryCommand;
use App\Services\Enquiries\ListOperationalEnquiries;
use App\Support\ApiErrorCode;
use App\Support\EnquiryIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EnquiryController extends V1Controller
{
    private const ENQUIRY_NOT_FOUND_MESSAGE = 'The requested enquiry was not found.';

    private const ATTACHMENT_CREDENTIAL_REQUIRED_MESSAGE = 'Authentication or an upload capability is required.';

    public function store(
        CreateEnquiryRequest $request,
        CreateEnquiry $creator,
        UploadCapabilityService $capabilities,
    ): JsonResponse {
        $input = $request->normalizedInput();
        $actor = $request->user();
        $command = new CreateEnquiryCommand(
            actor: $actor instanceof User ? $actor : null,
            name: $input->name,
            phone: $input->phone,
            email: $input->email,
            subject: $input->subject,
            message: $input->message,
            category: $input->category,
            productId: $input->productId,
            orderId: $input->orderId,
            attachment: $request->validatedAttachment(),
        );

        if ($request->validatedAttachment() !== null) {
            // The creation service owns its transaction and attachment
            // compensation; an outer transaction would roll back a queued
            // cleanup task when an inline attachment cannot be persisted.
            $enquiry = $creator->create($command);
            $uploadToken = null;
        } else {
            // Issuance shares the creation transaction so a failure can never
            // strand a committed enquiry without its upload capability.
            [$enquiry, $uploadToken] = DB::transaction(function () use ($creator, $capabilities, $command): array {
                $enquiry = $creator->create($command);
                $uploadToken = $capabilities->issueForEnquiry((int) $enquiry->getKey());

                return [$enquiry, $uploadToken];
            });
        }

        $enquiry->loadMissing(['product', 'order', 'attachments']);
        $headers = [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ];

        if ($uploadToken !== null) {
            $headers['X-Upload-Token'] = $uploadToken;
        }

        return (new EnquiryResource($enquiry))
            ->response()
            ->setStatusCode(201)
            ->withHeaders($headers);
    }

    public function meIndex(ListCustomerHistoryRequest $request): JsonResponse
    {
        $user = $this->customerActor($request);
        $pagination = $request->pagination();
        $paginator = Enquiry::query()
            ->where('user_id', $user->getKey())
            ->with(['product', 'order', 'attachments'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);
        $resources = $paginator->getCollection()
            ->map(fn (Enquiry $item): array => (new CustomerEnquirySummaryResource($item))->resolve($request))
            ->all();

        return $this->collectionResponse($resources, $paginator);
    }

    public function meShow(HttpRequest $request, string $identifier): JsonResponse
    {
        $user = $this->customerActor($request);
        $id = EnquiryIdentifier::decode($identifier);
        $enquiry = $id === null
            ? null
            : Enquiry::query()
                ->whereKey($id)
                ->where('user_id', $user->getKey())
                ->with(['product', 'order', 'attachments'])
                ->first();

        if ($enquiry === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, self::ENQUIRY_NOT_FOUND_MESSAGE, 404);
        }

        return (new EnquiryResource($enquiry))->response()->withHeaders($this->privateHeaders());
    }

    public function index(ListOperationalEnquiriesRequest $request, ListOperationalEnquiries $enquiries): JsonResponse
    {
        $paginator = $enquiries->paginate($request->normalizedQuery());
        $includeInternalNotes = $this->canManage($request);
        $resources = $paginator->getCollection()
            ->map(fn (Enquiry $enquiry): array => (new OperationalEnquiryResource($enquiry, $includeInternalNotes))->resolve($request))
            ->all();

        return $this->collectionResponse($resources, $paginator);
    }

    public function show(HttpRequest $request, string $identifier): JsonResponse
    {
        $enquiry = $this->findOperationalEnquiry($identifier);
        $enquiry->load([
            'product' => static fn ($product) => $product->withTrashed(),
            'order',
            'attachments',
        ]);

        return (new OperationalEnquiryResource($enquiry, $this->canManage($request)))
            ->response()
            ->withHeaders($this->privateHeaders());
    }

    public function close(CloseEnquiryRequest $request, string $identifier, CloseEnquiry $closer): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        $updated = $closer->close(
            $this->findOperationalEnquiry($identifier),
            $actor,
            $request->attributes->get('request_id'),
            $request->hasStaffInternalNotes(),
            $request->staffInternalNotes(),
        );
        $updated->load([
            'product' => static fn ($product) => $product->withTrashed(),
            'order',
            'attachments',
        ]);

        return (new OperationalEnquiryResource($updated, true))
            ->response()
            ->withHeaders($this->privateHeaders());
    }

    public function storeAttachment(
        UploadAttachmentRequest $request,
        string $identifier,
        UploadAttachment $uploader,
        UploadCapabilityService $capabilities,
    ): JsonResponse {
        $token = $this->anonymousAttachmentToken($request, $identifier, $capabilities);
        $enquiry = $this->findOperationalEnquiry($identifier);
        if ($token === null) {
            $this->authorizeAttachment($request, $enquiry);
        }
        $attachment = $uploader->forEnquiry($enquiry, $request->validatedAttachment(), $token);

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(201)
            ->withHeaders($this->privateHeaders());
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
    private function collectionResponse(array $resources, LengthAwarePaginator $paginator): JsonResponse
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

    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ];
    }

    private function findOperationalEnquiry(string $identifier): Enquiry
    {
        $id = EnquiryIdentifier::decode($identifier);
        $enquiry = $id === null ? null : Enquiry::query()->whereKey($id)->first();

        if ($enquiry === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, self::ENQUIRY_NOT_FOUND_MESSAGE, 404);
        }

        return $enquiry;
    }

    private function canManage(HttpRequest $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->checkPermissionTo('enquiries.manage');
    }

    private function anonymousAttachmentToken(
        UploadAttachmentRequest $request,
        string $identifier,
        UploadCapabilityService $capabilities,
    ): ?string {
        if ($request->user() instanceof User) {
            return null;
        }

        $token = $request->header('X-Upload-Token');
        $enquiryId = EnquiryIdentifier::decode($identifier);
        if ($token === null || $token === '' || $enquiryId === null || ! $capabilities->hasAvailableForEnquiry($token, $enquiryId)) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, self::ATTACHMENT_CREDENTIAL_REQUIRED_MESSAGE, 401);
        }

        return $token;
    }

    private function authorizeAttachment(UploadAttachmentRequest $request, Enquiry $enquiry): void
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new \LogicException('Anonymous attachment uploads require a validated capability.');
        }

        if ($actor->hasRole('CUSTOMER')) {
            if ((int) $enquiry->user_id !== (int) $actor->getKey()) {
                throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, self::ENQUIRY_NOT_FOUND_MESSAGE, 404);
            }

            return;
        }

        if (! $actor->checkPermissionTo('enquiries.manage')) {
            throw new ApiException(ApiErrorCode::FORBIDDEN, 'The authenticated actor cannot upload enquiry attachments.', 403);
        }

    }
}
