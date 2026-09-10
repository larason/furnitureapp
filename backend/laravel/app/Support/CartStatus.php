<?php

namespace App\Support;

/**
 * CLOSED Version 1 cart status vocabulary (phases/group-C-phases.md §3.8.12).
 * Cart status is independent of Order status.
 */
enum CartStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
