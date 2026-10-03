<?php

namespace App\Services\Requests;

use App\Exceptions\Api\ApiException;
use App\Models\FurnitureRequest;
use App\Support\ApiErrorCode;
use App\Support\ConcurrentTransaction;

final class UpdateFurnitureRequestOperationalFields
{
    public function __construct(private readonly ApplyFurnitureRequestStatusTransition $statusTransition) {}

    public function update(FurnitureRequest $request, FurnitureRequestOperationalUpdate $update): FurnitureRequest
    {
        return ConcurrentTransaction::run(function () use ($request, $update): FurnitureRequest {
            $locked = FurnitureRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested furniture request was not found.', 404);
            }

            if ($update->status !== null) {
                $this->statusTransition->apply($locked, $update->status);
            }

            if ($update->notesProvided && $locked->staff_internal_notes !== $update->notes) {
                $locked->staff_internal_notes = $update->notes;
            }

            if ($locked->isDirty()) {
                $locked->save();
            }

            return $locked;
        });
    }
}
