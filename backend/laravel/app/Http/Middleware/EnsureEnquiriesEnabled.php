<?php

namespace App\Http\Middleware;

use App\Support\NotImplementedResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Internal lifecycle gate for the frozen public ENQ-001 route. */
final class EnsureEnquiriesEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('enquiries.route_enabled')) {
            return NotImplementedResponse::make();
        }

        return $next($request);
    }
}
