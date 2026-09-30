<?php

namespace App\Services\Requests;

/**
 * Outcome of evaluating a Furniture Request status transition.
 */
enum RequestStatusTransitionOutcome: string
{
    case ALLOWED = 'ALLOWED';
    case IDEMPOTENT = 'IDEMPOTENT';
    case FORBIDDEN = 'FORBIDDEN';
}
