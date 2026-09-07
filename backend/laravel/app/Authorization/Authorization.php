<?php

namespace App\Authorization;

use App\Models\User;
use App\Support\PermissionName;

/**
 * Central RBAC authorization primitive for future domain policies.
 *
 * Deny-by-default: no authenticated identity or missing permission is
 * always false. Only the explicit role -> permission graph grants access;
 * role alone, client-supplied role/permission values, and wildcards are
 * never treated as authorization.
 */
class Authorization
{
    public function allows(?User $user, PermissionName|string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        $permission = $permission instanceof PermissionName ? $permission->value : $permission;

        return $user->checkPermissionTo($permission);
    }
}
