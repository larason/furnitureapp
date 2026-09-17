<?php

namespace App\Http\Middleware;

use App\Support\RoleName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard for Administrative role access.
 *
 * The frozen contract classifies `/admin/*`, `/users`, catalog writes
 * (`/products`, `/categories`) and audit access as ADMINISTRATIVE (Admin
 * only). Phase 4.9 establishes the canonical Laravel RBAC role boundary;
 * Phase 4.10 adds resource/action/state policy checks.
 *
 * Only the Admin role with an ACTIVE application account state is admitted to
 * this boundary. Individual permissions and business-state checks remain
 * enforced by Phase 4.10.
 *
 * The preceding `clerk.auth` middleware guarantees the request is authenticated
 * before this middleware runs, so failures surface as 403 (authenticated
 * identity, insufficient authority), never as 401. Phase 2.7 maps this to
 * the frozen `FORBIDDEN` error contract.
 */
final class AdministrativeAccess
{
    private const ACTIVE_ACCOUNT_STATE = 'ACTIVE';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasRole(RoleName::ADMIN->value) || $user->account_state !== self::ACTIVE_ACCOUNT_STATE) {
            throw new AuthorizationException('The requested administrative operation is not available.');
        }

        return $next($request);
    }
}
