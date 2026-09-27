<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\RoleName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CustomerCartAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasRole(RoleName::CUSTOMER->value)) {
            throw new AuthorizationException('Customer cart access is required.');
        }

        return $next($request);
    }
}
