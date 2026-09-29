<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the frozen `POST /api/v1/checkout` route. Phase 7.4 DELIVERY billing
 * snapshot persistence is unresolved, so the endpoint must not operate as a
 * PICKUP-only public route. This runs after authentication, CUSTOMER
 * authorization, and throttling, and before request-body validation so a
 * gated route always returns the stub response regardless of body validity.
 */
final class EnsureCheckoutEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('checkout.route_enabled')) {
            return response()->json(['status' => 'not_implemented'], 501);
        }

        return $next($request);
    }
}
