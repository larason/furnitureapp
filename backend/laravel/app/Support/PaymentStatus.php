<?php

namespace App\Support;

/**
 * CLOSED Version 1 payment status vocabulary.
 * Separate from Order status; backend-controlled.
 */
enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
}
