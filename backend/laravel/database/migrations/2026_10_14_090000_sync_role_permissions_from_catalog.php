<?php

use App\Support\PermissionCatalog;
use App\Support\PermissionName;
use App\Support\RoleName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reconciles role permissions on databases that were seeded before a permission existed.
 *
 * `PermissionCatalog` is the single source of truth for the V1 role -> permission mapping,
 * but `RbacSeeder` is the only thing that writes it to the database and it runs through
 * `db:seed`, which a deployment does not run. A permission added to the catalog without a
 * matching migration therefore reaches the code but never the database.
 *
 * That fails closed and silently: `checkPermissionTo()` cannot find a permission that does
 * not exist, so a capability such as `notifications.read_operational` turns into a 403 for
 * every existing staff and admin account while the job that creates their operational
 * notifications keeps running.
 *
 * Granting is driven by the catalog rather than hard-coded, so the next permission does
 * not reintroduce this. The grant is additive: a migration that also revoked could take
 * away something an operator granted deliberately, and nothing here needs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = (string) config('auth.defaults.guard');

        foreach (RoleName::cases() as $role) {
            $permissions = PermissionCatalog::forRole($role);

            // CUSTOMER is deliberately empty, and must stay empty.
            if ($permissions === []) {
                continue;
            }

            $this->grant($role, $permissions, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Additive by design: see the class docblock.
    }

    /** @param array<int, PermissionName> $permissions */
    private function grant(RoleName $role, array $permissions, string $guard): void
    {
        $record = Role::firstOrCreate(['name' => $role->value, 'guard_name' => $guard]);

        $record->givePermissionTo(array_map(
            fn (PermissionName $permission): PermissionContract => Permission::findOrCreate($permission->value, $guard),
            $permissions,
        ));
    }
};
