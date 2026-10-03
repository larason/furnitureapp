<?php

namespace App\Services\Requests;

use App\Exceptions\Api\ApiException;
use App\Models\FurnitureRequest;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\ApiErrorCode;
use App\Support\AuditAction;
use App\Support\AuditResourceType;
use App\Support\ConcurrentTransaction;
use App\Support\FurnitureRequestIdentifier;

final class UpdateFurnitureRequestOperationalFields
{
    public function __construct(
        private readonly ApplyFurnitureRequestStatusTransition $statusTransition,
        private readonly AuditRecorder $audit,
    ) {}

    public function update(
        FurnitureRequest $request,
        FurnitureRequestOperationalUpdate $update,
        User $actor,
        ?string $requestId,
    ): FurnitureRequest {
        return ConcurrentTransaction::run(function () use ($request, $update, $actor, $requestId): FurnitureRequest {
            $locked = FurnitureRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested furniture request was not found.', 404);
            }

            $previousStatus = $locked->request_status;

            if ($update->status !== null) {
                $this->statusTransition->apply($locked, $update->status);
            }

            if ($update->notesProvided && $locked->staff_internal_notes !== $update->notes) {
                $locked->staff_internal_notes = $update->notes;
            }

            if ($locked->isDirty()) {
                $locked->save();
            }

            if ($update->status !== null && $previousStatus !== $locked->request_status) {
                $this->audit->record(
                    $actor,
                    AuditAction::REQUEST_STATUS_CHANGED,
                    AuditResourceType::REQUEST,
                    FurnitureRequestIdentifier::encode($locked),
                    ['request_status' => $previousStatus->value],
                    ['request_status' => $locked->request_status->value],
                    $requestId,
                );
            }

            return $locked;
        }, true);
    }
}
