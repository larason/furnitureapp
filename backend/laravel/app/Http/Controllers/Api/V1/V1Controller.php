<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\NotImplementedResponse;
use Illuminate\Http\JsonResponse;

abstract class V1Controller extends Controller
{
    /**
     * Placeholder response used by routing-foundation stubs.
     *
     * Domain implementation phases replace each stub method with the frozen
     * contract behavior. 501 signals that the route exists but is not yet
     * implemented; it is not part of the external contract.
     */
    protected function notImplemented(): JsonResponse
    {
        return NotImplementedResponse::make();
    }
}
