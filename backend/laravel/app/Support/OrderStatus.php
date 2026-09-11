<?php

namespace App\Support;

/**
 * CLOSED Version 1 order status vocabulary (phases/group-C-phases.md §3.9.7).
 * Backend-controlled transitions only; transitions belong to a later phase.
 */
enum OrderStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID = 'PAID';
    case ACCEPTED = 'ACCEPTED';
    case PROCESSING = 'PROCESSING';
    case READY_FOR_PICKUP = 'READY_FOR_PICKUP';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
