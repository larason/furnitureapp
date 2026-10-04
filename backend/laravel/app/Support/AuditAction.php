<?php

namespace App\Support;

/**
 * CLOSED privileged-audit action names (api-contract.md §30.16).
 */
enum AuditAction: string
{
    case INVENTORY_ADJUSTED = 'INVENTORY_ADJUSTED';
    case DELIVERY_FEE_FINALIZED = 'DELIVERY_FEE_FINALIZED';
    case REQUEST_STATUS_CHANGED = 'REQUEST_STATUS_CHANGED';
    case ENQUIRY_STATUS_CHANGED = 'ENQUIRY_STATUS_CHANGED';
}
