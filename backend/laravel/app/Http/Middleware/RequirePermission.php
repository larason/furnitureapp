<?php

namespace App\Http\Middleware;

use App\Support\PermissionName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $this->isAllowedPermission($permission) || $user === null || ! $user->checkPermissionTo($permission)) {
            throw new AuthorizationException('The requested permission is not available.');
        }

        return $next($request);
    }

    private function isAllowedPermission(string $permission): bool
    {
        return PermissionName::tryFrom($permission) !== null;
    }
}
