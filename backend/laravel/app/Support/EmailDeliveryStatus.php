<?php

namespace App\Support;

enum EmailDeliveryStatus: string
{
    case PENDING = 'PENDING';
    case SENDING = 'SENDING';
    case ACCEPTED = 'ACCEPTED';
    case FAILED = 'FAILED';
    case SKIPPED = 'SKIPPED';
}
