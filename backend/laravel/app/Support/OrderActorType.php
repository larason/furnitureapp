<?php

namespace App\Support;

/**
 * CLOSED Version 1 actor source for order status history (phases/group-C-phases.md §3.11.9).
 * server-derived; never client-provided.
 */
enum OrderActorType: string
{
    case CUSTOMER = 'CUSTOMER';
    case STAFF = 'STAFF';
    case ADMIN = 'ADMIN';
    case SYSTEM = 'SYSTEM';
}
