<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\RoleName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional-auth actor boundary for public customer submissions (REQ-001).
 *
 * Anonymous callers are permitted. When a bearer token resolves a local user,
 * only a pure CUSTOMER may create a customer-owned Request; STAFF/ADMIN (and
 * any non-customer identity) are rejected instead of being treated as
 * anonymous. Operational access is never customer submission authority.
 */
final class CustomerSubmissionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $this->isCustomer($user)) {
            throw new AuthorizationException('Customer submission access is required.');
        }

        return $next($request);
    }

    private function isCustomer(User $user): bool
    {
        return $user->hasRole(RoleName::CUSTOMER->value)
            && ! $user->hasAnyRole([RoleName::STAFF->value, RoleName::ADMIN->value]);
    }
}
