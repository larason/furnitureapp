<?php

namespace App\Support;

enum PaymentWebhookProcessingStatus: string
{
    case RECEIVED = 'RECEIVED';
    case PROCESSED = 'PROCESSED';
    case FAILED = 'FAILED';
}
