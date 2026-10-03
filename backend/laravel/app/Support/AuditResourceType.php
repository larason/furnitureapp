<?php

namespace App\Support;

/**
 * CLOSED privileged-audit resource types (api-contract.md §30.16).
 */
enum AuditResourceType: string
{
    case INVENTORY = 'inventory';
    case ORDER = 'order';
    case REQUEST = 'request';
    case ENQUIRY = 'enquiry';
}
