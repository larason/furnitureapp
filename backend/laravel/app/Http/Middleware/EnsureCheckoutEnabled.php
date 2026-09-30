<?php

namespace App\Http\Middleware;

use App\Support\NotImplementedResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the frozen `POST /api/v1/checkout` route. Phase 7.4 DELIVERY billing
 * snapshot persistence is unresolved, so the endpoint must not operate as a
 * PICKUP-only public route. This runs after authentication and CUSTOMER
 * authorization but before the dedicated checkout throttle and request-body
 * validation: while the route is permanently gated it must return the stable
 * stub for every request (not a 429 after the per-user budget is consumed),
 * regardless of body validity.
 */
final class EnsureCheckoutEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('checkout.route_enabled')) {
            return NotImplementedResponse::make();
        }

        return $next($request);
    }
}
