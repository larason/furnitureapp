<?php

namespace App\Support;

/**
 * CLOSED Version 1 furniture request status vocabulary.
 * Server-controlled transitions; SUBMITTED → IN_REVIEW → CLOSED with
 * direct SUBMITTED → CLOSED also permitted.
 */
enum RequestStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case IN_REVIEW = 'IN_REVIEW';
    case CLOSED = 'CLOSED';
}
