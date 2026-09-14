<?php

namespace App\Support;

/**
 * CLOSED Version 1 enquiry triage category (frozen API contract §27).
 * Nullable; server-validated, never client-authoritative beyond intake.
 */
enum EnquiryCategory: string
{
    case GENERAL = 'GENERAL';
    case PRODUCT = 'PRODUCT';
    case DELIVERY = 'DELIVERY';
    case OTHER = 'OTHER';
}
