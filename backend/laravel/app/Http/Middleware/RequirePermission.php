<?php

namespace App\Http\Middleware;

use App\Support\PermissionName;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->hasAllowedPermission($user, $permissions)) {
            throw new AuthorizationException('The requested permission is not available.');
        }

        return $next($request);
    }

    /** @param list<string> $permissions */
    private function hasAllowedPermission(object $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (PermissionName::tryFrom($permission) !== null && $user->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
