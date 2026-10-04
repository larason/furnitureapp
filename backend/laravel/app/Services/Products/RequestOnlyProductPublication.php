<?php

namespace App\Services\Products;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use App\Support\ProductType;

final class RequestOnlyProductPublication
{
    public function assertAllowed(ProductType $productType, bool $isPublished): void
    {
        if ((bool) config('commerce.request_only') && $productType === ProductType::IN_STOCK && $isPublished) {
            throw new ApiException(ApiErrorCode::BUSINESS_RULE_VIOLATION, 'In-stock products cannot be published while request-only mode is enabled.', 422, 'is_published');
        }
    }
}
