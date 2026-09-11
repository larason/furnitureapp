<?php

namespace App\Support;

/**
 * CLOSED Version 1 delivery-fee lifecycle vocabulary (phases/group-C-phases.md §3.9.11).
 */
enum DeliveryFeeStatus: string
{
    case PENDING = 'PENDING';
    case FINALIZED = 'FINALIZED';
}
