<?php

namespace App\Services\Requests;

use App\Exceptions\InvalidRequestStatusTransition;
use App\Models\FurnitureRequest;
use App\Support\RequestStatus;

final class ApplyFurnitureRequestStatusTransition
{
    public function apply(FurnitureRequest $request, RequestStatus $target): void
    {
        $outcome = RequestStatusMachine::decide($request->request_status, $target);

        if ($outcome === RequestStatusTransitionOutcome::Forbidden) {
            throw new InvalidRequestStatusTransition($request->request_status, $target);
        }

        if ($outcome === RequestStatusTransitionOutcome::Allowed) {
            $request->request_status = $target;
        }
    }
}
