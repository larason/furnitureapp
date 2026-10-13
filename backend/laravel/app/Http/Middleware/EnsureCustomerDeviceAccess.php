<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\RoleName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts device registration and deactivation to authenticated customers.
 *
 * Staff and admin identities are refused: registering a device is a
 * customer-account capability, and operational staff must not be able to
 * associate a device with a customer account.
 */
final class EnsureCustomerDeviceAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && (! $user->hasRole(RoleName::CUSTOMER->value)
                || $user->hasAnyRole([RoleName::STAFF->value, RoleName::ADMIN->value]))) {
            throw new AuthorizationException('Customer device access is required.');
        }

        return $next($request);
    }
}
