<?php

namespace App\Services\Attachments;

use App\Exceptions\Api\ApiException;
use App\Exceptions\AttachmentCleanupRequired;
use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Support\ApiErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UploadAttachment
{
    public function __construct(
        private readonly AttachFileToParent $attachments,
        private readonly UploadCapabilityService $capabilities,
    ) {}

    public function forRequest(FurnitureRequest $request, ValidatedAttachment $file, ?string $token): Attachment
    {
        try {
            return DB::transaction(function () use ($request, $file, $token): Attachment {
                if ($token !== null && ! $this->capabilities->consumeForRequest($token, (int) $request->getKey())) {
                    throw new ApiException(ApiErrorCode::INVALID_AUTHENTICATION, 'The upload capability is invalid or expired.', 401, 'X-Upload-Token');
                }

                $lockedRequest = FurnitureRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();
                if ($lockedRequest === null) {
                    throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested furniture request was not found.', 404);
                }

                if ($lockedRequest->attachments()->exists()) {
                    throw new ApiException(ApiErrorCode::CONFLICT, 'An attachment already exists for this furniture request.', 409);
                }

                return $this->attachments->attachRequest($lockedRequest, $file);
            });
        } catch (AttachmentCleanupRequired $exception) {
            $this->recover($exception);

            throw $exception;
        }
    }

    public function forEnquiry(Enquiry $enquiry, ValidatedAttachment $file, ?string $token): Attachment
    {
        try {
            return DB::transaction(function () use ($enquiry, $file, $token): Attachment {
                if ($token !== null && ! $this->capabilities->consumeForEnquiry($token, (int) $enquiry->getKey())) {
                    throw new ApiException(ApiErrorCode::INVALID_AUTHENTICATION, 'The upload capability is invalid or expired.', 401, 'X-Upload-Token');
                }

                $lockedEnquiry = Enquiry::query()->whereKey($enquiry->getKey())->lockForUpdate()->first();
                if ($lockedEnquiry === null) {
                    throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested enquiry was not found.', 404);
                }

                if ($lockedEnquiry->attachments()->exists()) {
                    throw new ApiException(ApiErrorCode::CONFLICT, 'An attachment already exists for this enquiry.', 409);
                }

                return $this->attachments->attachEnquiry($lockedEnquiry, $file);
            });
        } catch (AttachmentCleanupRequired $exception) {
            $this->recover($exception);

            throw $exception;
        }
    }

    private function recover(AttachmentCleanupRequired $exception): void
    {
        try {
            $this->attachments->queueCleanup($exception->storageDisk, $exception->storageKey);
        } catch (Throwable $cleanupTaskFailure) {
            Log::warning('attachment.cleanup_task_persist_failed', [
                'exception' => $cleanupTaskFailure::class,
            ]);
            $this->attachments->recoverCleanup($exception->storageDisk, $exception->storageKey);
        }
    }
}
