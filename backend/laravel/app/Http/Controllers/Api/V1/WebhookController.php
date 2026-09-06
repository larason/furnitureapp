<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class WebhookController extends V1Controller
{
    public function handlePaymentProvider(): JsonResponse
    {
        return $this->notImplemented();
    }
}
