<?php

namespace App\Http\Middleware;

use App\Support\RoleName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StaffOrAdminAccess
{
    private const ACTIVE_ACCOUNT_STATE = 'ACTIVE';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasAnyRole([RoleName::STAFF->value, RoleName::ADMIN->value]) || $user->account_state !== self::ACTIVE_ACCOUNT_STATE) {
            throw new AuthorizationException('The requested operational operation is not available.');
        }

        return $next($request);
    }
}
