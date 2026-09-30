<?php

namespace App\Services\Requests;

/**
 * Outcome of evaluating a Furniture Request status transition.
 */
enum RequestStatusTransitionOutcome: string
{
    case Allowed = 'ALLOWED';
    case Idempotent = 'IDEMPOTENT';
    case Forbidden = 'FORBIDDEN';
}
