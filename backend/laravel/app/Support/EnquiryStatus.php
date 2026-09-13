<?php

namespace App\Support;

/**
 * CLOSED Version 1 enquiry lifecycle vocabulary.
 * Server-controlled; OPEN → CLOSED.
 */
enum EnquiryStatus: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
}
