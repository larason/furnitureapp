<?php

namespace App\Http\Middleware;

use App\Support\NotImplementedResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the frozen `POST /api/v1/requests` route.
 *
 * Phase 10.2 (complete validation matrix), Phase 10.4 (MADE_TO_ORDER linked
 * product eligibility), and Phase 10.6 (attachment handling) are not yet
 * implemented, so the public REQ-001 route must not operate as a partial V1
 * endpoint. This runs after optional authentication and actor classification
 * but before the submission throttle, so a gated route returns the stable stub
 * without consuming the anonymous-submit budget. The gate is an internal,
 * test-only flag and is deliberately not environment-driven, so it can never
 * be exposed from configuration. Tests opt in in-process.
 */
final class EnsureFurnitureRequestsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('requests.route_enabled')) {
            return NotImplementedResponse::make();
        }

        return $next($request);
    }
}
