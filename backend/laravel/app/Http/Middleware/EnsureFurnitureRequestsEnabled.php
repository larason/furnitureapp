<?php

namespace App\Http\Middleware;

use App\Support\NotImplementedResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Internal lifecycle gate for the frozen public REQ-001 route. */
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
