<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\ApiException;
use App\Services\Cart\GuestCartTransport;
use App\Support\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateGuestCartMutation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasCookie(GuestCartTransport::COOKIE)) {
            $origin = (string) $request->headers->get('Origin', '');
            $allowedOrigins = config('cors.allowed_origins', []);
            $fetchSite = strtolower((string) $request->headers->get('Sec-Fetch-Site', ''));

            if ($origin === '' || ! is_array($allowedOrigins) || ! in_array($origin, $allowedOrigins, true)) {
                throw new ApiException(ApiErrorCode::FORBIDDEN, 'The request origin is not allowed.', 403);
            }

            if ($fetchSite === 'cross-site') {
                throw new ApiException(ApiErrorCode::FORBIDDEN, 'Cross-site cart mutation is not allowed.', 403);
            }
        }

        return $next($request);
    }
}
