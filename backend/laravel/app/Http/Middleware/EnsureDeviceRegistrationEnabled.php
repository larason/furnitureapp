<?php

namespace App\Http\Middleware;

use App\Support\NotImplementedResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the device-registration routes behind an independent feature flag.
 *
 * Token management is authorized by the contract but is not the same decision as
 * sending customer push notifications, so registration can be enabled or
 * suspended without touching notification fan-out. While the flag is off the
 * routes report the stable not-implemented response instead of accepting tokens
 * the business has not switched on.
 */
final class EnsureDeviceRegistrationEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('notifications.push.registration_enabled')) {
            return NotImplementedResponse::make();
        }

        return $next($request);
    }
}
