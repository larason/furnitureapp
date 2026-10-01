<?php

namespace App\Services\Enquiries;

use App\Exceptions\Api\ApiException;
use App\Models\Order;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\OrderIdentifier;

/**
 * Resolves an optional ENQ-001 linked Order for an authenticated Customer.
 *
 * Ownership is authoritative: an unknown or foreign Order resolves to the same
 * masked `404 ORDER_NOT_FOUND`, so cross-customer Order existence is never
 * disclosed. Anonymous callers never reach this resolver (rejected at the
 * schema boundary). It never mutates the Order.
 */
final class OwnedEnquiryOrderResolver
{
    public function resolve(?string $orderIdentifier, User $customer): ?Order
    {
        if ($orderIdentifier === null) {
            return null;
        }

        $publicId = OrderIdentifier::decode($orderIdentifier);

        $order = $publicId === null
            ? null
            : Order::query()
                ->where('public_id', $publicId)
                ->where('customer_id', $customer->getKey())
                ->first();

        if ($order === null) {
            throw new ApiException(ApiErrorCode::ORDER_NOT_FOUND, 'The requested order was not found.', 404, 'order_id');
        }

        return $order;
    }
}
