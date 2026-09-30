<?php

namespace App\Services\Requests;

use App\Support\RequestStatus;

/**
 * Pure, deterministic Version 1 Furniture Request status state machine.
 *
 * Allowed: SUBMITTED → IN_REVIEW, SUBMITTED → CLOSED, IN_REVIEW → CLOSED.
 * Same status is an idempotent no-op; CLOSED is terminal (no reopen).
 * It depends on nothing else — no database, actor, clock, or randomness.
 */
final class RequestStatusMachine
{
    public static function decide(RequestStatus $current, RequestStatus $target): RequestStatusTransitionOutcome
    {
        if ($current === $target) {
            return RequestStatusTransitionOutcome::Idempotent;
        }

        return in_array($target, self::allowedTargets($current), true)
            ? RequestStatusTransitionOutcome::Allowed
            : RequestStatusTransitionOutcome::Forbidden;
    }

    /** @return list<RequestStatus> */
    public static function allowedTargets(RequestStatus $current): array
    {
        return match ($current) {
            RequestStatus::SUBMITTED => [RequestStatus::IN_REVIEW, RequestStatus::CLOSED],
            RequestStatus::IN_REVIEW => [RequestStatus::CLOSED],
            RequestStatus::CLOSED => [],
        };
    }
}
