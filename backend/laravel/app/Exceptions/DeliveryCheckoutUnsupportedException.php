<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;

/**
 * Internal Phase 7.4 blocker: DELIVERY checkout persistence (billing snapshot
 * model gap) is unresolved. It extends ApiException so the failure is mapped to
 * a canonical V1 error envelope (never a generic 500) regardless of route-gate
 * state; it is only reachable while the frozen route is gated.
 */
final class DeliveryCheckoutUnsupportedException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            ApiErrorCode::BUSINESS_RULE_VIOLATION,
            'DELIVERY checkout is temporarily unavailable.',
            422,
            'fulfillment_type',
        );
    }
}
