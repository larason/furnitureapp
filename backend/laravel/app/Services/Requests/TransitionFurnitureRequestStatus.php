<?php

namespace App\Services\Requests;

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
    public function __construct(private readonly ApplyFurnitureRequestStatusTransition $statusTransition) {}

    public function transition(FurnitureRequest $request, RequestStatus $target): FurnitureRequest
    {
        return ConcurrentTransaction::run(function () use ($request, $target): FurnitureRequest {
            $locked = FurnitureRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            $this->statusTransition->apply($locked, $target);

            if (! $locked->isDirty()) {
                return $locked;
            }

            $locked->save();

            return $locked;
        });
    }
}
