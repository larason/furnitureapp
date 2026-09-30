<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Single owner of the "endpoint not yet activated" stub response, used by
 * `V1Controller::notImplemented()` and the CHK-001 route gate so the shape
 * cannot drift between them. This marker is not part of the frozen V1 error
 * envelope: it only appears on routes that are deliberately not activated.
 */
final class NotImplementedResponse
{
    public static function make(): JsonResponse
    {
        return response()->json(['status' => 'not_implemented'], 501);
    }
}
