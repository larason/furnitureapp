<?php

namespace App\Http\Middleware;

use App\Support\RoleName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard for Staff/operational role access.
 *
 * The frozen contract classifies `/orders`, `/inventory`, and the
 * operational handling of `/requests` and `/enquiries` as OPERATIONAL
 * (Staff/Admin). Phase 4.9 establishes the canonical Laravel RBAC role
 * boundary; Phase 4.10 adds resource/action/state policy checks.
 *
 * Staff and Admin roles with an ACTIVE application account state are admitted
 * to the operational boundary. Individual permissions and business-state
 * checks remain enforced by Phase 4.10.
 *
 * The preceding `clerk.auth` middleware guarantees the request is authenticated
 * before this middleware runs, so failures surface as 403 (authenticated
 * identity, insufficient authority), never as 401. Phase 2.7 maps this to
 * the frozen `FORBIDDEN` error contract.
 */
final class OperationalAccess
{
    private const ACTIVE_ACCOUNT_STATE = 'ACTIVE';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! $user->hasAnyRole([RoleName::STAFF->value, RoleName::ADMIN->value]) || $user->account_state !== self::ACTIVE_ACCOUNT_STATE) {
            throw new AuthorizationException('The requested operational operation is not available.');
        }

        return $next($request);
    }
}
