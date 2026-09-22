<?php

namespace App\Support;

/**
 * CLOSED privileged-audit action names (api-contract.md §30.16).
 */
enum AuditAction: string
{
    case INVENTORY_ADJUSTED = 'INVENTORY_ADJUSTED';
}
