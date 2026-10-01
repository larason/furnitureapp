<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;

/**
 * A publicly visible Product was supplied to REQ-001 but is `IN_STOCK`, so it
 * belongs to the purchase workflow, not the made-to-order Request workflow.
 * Mapped to the frozen `409 PRODUCT_NOT_REQUESTABLE` response.
 */
final class ProductNotRequestable extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            ApiErrorCode::PRODUCT_NOT_REQUESTABLE,
            'Only made-to-order products can be linked to a furniture request.',
            409,
            'product_id',
        );
    }
}
