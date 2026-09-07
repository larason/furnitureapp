<?php

namespace App\Support;

/**
 * CLOSED Version 1 user roles (api-contract.md §17.1/§18, api-resources.md §12.1).
 * Adding a role is a V1 compatibility decision.
 */
enum RoleName: string
{
    case CUSTOMER = 'CUSTOMER';
    case STAFF = 'STAFF';
    case ADMIN = 'ADMIN';
}
