<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use App\Support\PermissionName;
use App\Support\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        foreach (PermissionName::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => $guard]);
        }

        foreach (RoleName::cases() as $role) {
            $roleRecord = Role::firstOrCreate(['name' => $role->value, 'guard_name' => $guard]);
            $roleRecord->syncPermissions(array_map(
                fn (PermissionName $permission) => $permission->value,
                PermissionCatalog::forRole($role),
            ));
        }
    }
}
