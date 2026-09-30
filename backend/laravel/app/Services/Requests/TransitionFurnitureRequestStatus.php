<?php

namespace App\Services\Requests;

use App\Exceptions\InvalidRequestStatusTransition;
use App\Models\FurnitureRequest;
use App\Support\ConcurrentTransaction;
use App\Support\RequestStatus;

/**
 * Atomic, race-safe persistence boundary for a Furniture Request status change.
 *
 * It locks only the Request row, re-reads the authoritative current status
 * inside the transaction, evaluates the pure state machine against it, and
 * persists only `request_status`. Same-state requests are idempotent no-ops
 * with no UPDATE (so `updated_at` is untouched). It creates no Order, Payment,
 * inventory, quote, or notification effect.
 */
final class TransitionFurnitureRequestStatus
{
    public function transition(FurnitureRequest $request, RequestStatus $target): FurnitureRequest
    {
        return ConcurrentTransaction::run(function () use ($request, $target): FurnitureRequest {
            $locked = FurnitureRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            $outcome = RequestStatusMachine::decide($locked->request_status, $target);

            if ($outcome === RequestStatusTransitionOutcome::IDEMPOTENT) {
                return $locked;
            }

            if ($outcome === RequestStatusTransitionOutcome::FORBIDDEN) {
                throw new InvalidRequestStatusTransition($locked->request_status, $target);
            }

            $locked->request_status = $target;
            $locked->save();

            return $locked;
        });
    }
}
