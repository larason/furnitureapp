<?php

namespace App\Support;

/**
 * Single source of truth for the V1 role -> permission assignments
 * (phases/group-C-phases.md §3.2.3/§3.2.4). Used by the RBAC seeder and tests.
 */
class PermissionCatalog
{
    public static function all(): array
    {
        return [
            PermissionName::PRODUCTS_VIEW,
            PermissionName::PRODUCTS_MANAGE,
            PermissionName::INVENTORY_VIEW,
            PermissionName::INVENTORY_MANAGE,
            PermissionName::ORDERS_VIEW_OPERATIONAL,
            PermissionName::ORDERS_ACCEPT,
            PermissionName::ORDERS_PROCESS,
            PermissionName::ORDERS_READY_FOR_PICKUP,
            PermissionName::ORDERS_SHIP,
            PermissionName::ORDERS_DELIVER,
            PermissionName::REQUESTS_VIEW,
            PermissionName::REQUESTS_MANAGE,
            PermissionName::ENQUIRIES_VIEW,
            PermissionName::ENQUIRIES_MANAGE,
            PermissionName::STAFF_APPROVE,
            PermissionName::STAFF_MANAGE,
            PermissionName::USERS_MANAGE_AUTHORIZED,
        ];
    }

    public static function forRole(RoleName $role): array
    {
        return match (true) {
            $role === RoleName::CUSTOMER => [],
            $role === RoleName::STAFF => self::staffPermissions(),
            $role === RoleName::ADMIN => self::all(),
        };
    }

    private static function staffPermissions(): array
    {
        return [
            PermissionName::PRODUCTS_VIEW,
            PermissionName::PRODUCTS_MANAGE,
            PermissionName::INVENTORY_VIEW,
            PermissionName::INVENTORY_MANAGE,
            PermissionName::ORDERS_VIEW_OPERATIONAL,
            PermissionName::ORDERS_ACCEPT,
            PermissionName::ORDERS_PROCESS,
            PermissionName::ORDERS_READY_FOR_PICKUP,
            PermissionName::ORDERS_SHIP,
            PermissionName::ORDERS_DELIVER,
            PermissionName::REQUESTS_VIEW,
            PermissionName::REQUESTS_MANAGE,
            PermissionName::ENQUIRIES_VIEW,
            PermissionName::ENQUIRIES_MANAGE,
        ];
    }
}
