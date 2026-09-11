<?php

namespace App\Support;

/**
 * CLOSED Version 1 order fulfillment vocabulary (phases/group-C-phases.md §3.9.10).
 */
enum FulfillmentType: string
{
    case PICKUP = 'PICKUP';
    case DELIVERY = 'DELIVERY';
}
