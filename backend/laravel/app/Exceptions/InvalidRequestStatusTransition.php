<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use App\Support\RequestStatus;

/**
 * A recognized Request status was requested, but the current → target
 * transition is not part of the frozen V1 lifecycle (for example a backward or
 * post-CLOSED transition). Mapped to a canonical `409 CONFLICT` envelope.
 */
final class InvalidRequestStatusTransition extends ApiException
{
    public function __construct(RequestStatus $current, RequestStatus $target)
    {
        parent::__construct(
            ApiErrorCode::CONFLICT,
            "The request status cannot change from {$current->value} to {$target->value}.",
            409,
            'request_status',
        );
    }
}
