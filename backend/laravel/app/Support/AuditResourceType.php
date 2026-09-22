<?php

namespace App\Support;

/**
 * CLOSED privileged-audit resource types (api-contract.md §30.16).
 */
enum AuditResourceType: string
{
    case INVENTORY = 'inventory';
}
