<?php

namespace App\Support;

/**
 * Closed V1 notification type registry.
 *
 * PAYMENT_* types are intentionally deferred to Phase Group H.
 * Do not add payment-specific cases here until the payment contract
 * is finalised and the frozen V1 API contract is updated.
 */
enum NotificationType: string
{
    // Order lifecycle — customer-facing
    case ORDER_RECEIVED = 'ORDER_RECEIVED';
    case ORDER_ACCEPTED = 'ORDER_ACCEPTED';
    case ORDER_PROCESSING = 'ORDER_PROCESSING';
    case ORDER_READY_FOR_PICKUP = 'ORDER_READY_FOR_PICKUP';
    case ORDER_SHIPPED = 'ORDER_SHIPPED';
    case ORDER_DELIVERED = 'ORDER_DELIVERED';
    case ORDER_COMPLETED = 'ORDER_COMPLETED';
    case ORDER_CANCELLED = 'ORDER_CANCELLED';

    // Operational — internal/staff
    case NEW_ORDER = 'NEW_ORDER';
    case NEW_MADE_TO_ORDER_REQUEST = 'NEW_MADE_TO_ORDER_REQUEST';
    case NEW_ENQUIRY = 'NEW_ENQUIRY';

    public function isOrderLifecycle(): bool
    {
        return in_array($this, [
            self::ORDER_RECEIVED,
            self::ORDER_ACCEPTED,
            self::ORDER_PROCESSING,
            self::ORDER_READY_FOR_PICKUP,
            self::ORDER_SHIPPED,
            self::ORDER_DELIVERED,
            self::ORDER_COMPLETED,
            self::ORDER_CANCELLED,
        ], true);
    }

    public function isOperational(): bool
    {
        return in_array($this, [
            self::NEW_ORDER,
            self::NEW_MADE_TO_ORDER_REQUEST,
            self::NEW_ENQUIRY,
        ], true);
    }
}
