<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleName;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The role -> permission mapping has to reach databases that were seeded before a
 * permission was added to the catalog.
 *
 * `RbacSeeder` writes that mapping, but only `db:seed` runs it, so a deployment that runs
 * `php artisan migrate` alone keeps the old mapping. Because `checkPermissionTo()` fails
 * closed for a permission it cannot find, the missing row is not a no-op: it is a 403.
 */
final class PermissionCatalogMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATIONAL_NOTIFICATIONS = 'notifications.read_operational';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_it_restores_a_permission_missing_from_an_already_seeded_database(): void
    {
        $this->rewindToTheStateBeforeThePermission();

        $this->assertFalse(
            DB::table('permissions')->where('name', self::OPERATIONAL_NOTIFICATIONS)->exists(),
            'Precondition: this database was seeded before the permission existed at all.',
        );

        $this->reconcile();

        $this->assertTrue(Role::findByName(RoleName::STAFF->value)->hasPermissionTo(self::OPERATIONAL_NOTIFICATIONS));
        $this->assertTrue(Role::findByName(RoleName::ADMIN->value)->hasPermissionTo(self::OPERATIONAL_NOTIFICATIONS));
    }

    public function test_staff_regain_the_operational_notification_capability_end_to_end(): void
    {
        $this->rewindToTheStateBeforeThePermission();
        $this->reconcile();

        // `hasPermissionTo` reads the same cached registrar that the controller does, so
        // this fails unless the migration also cleared the cache.
        $staff = $this->staffWithTheRoleAttached();

        $this->assertTrue($staff->fresh()->checkPermissionTo(self::OPERATIONAL_NOTIFICATIONS));
        $this->assertSame(
            1,
            DB::table('permissions')->where('name', self::OPERATIONAL_NOTIFICATIONS)->count(),
            'Reconciling must not duplicate the permission.',
        );
    }

    public function test_it_never_grants_a_capability_to_the_customer_role(): void
    {
        $this->rewindToTheStateBeforeThePermission();
        $this->reconcile();

        $customer = Role::findByName(RoleName::CUSTOMER->value);

        $this->assertSame([], PermissionCatalog::forRole(RoleName::CUSTOMER));
        $this->assertFalse($customer->hasPermissionTo(self::OPERATIONAL_NOTIFICATIONS));
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $before = DB::table('role_has_permissions')->count();

        $this->reconcile();
        $this->reconcile();

        $this->assertSame($before, DB::table('role_has_permissions')->count());
    }

    /**
     * Removes the permission the way a database seeded before it existed looks.
     *
     * `role_has_permissions.permission_id` cascades, so deleting the permission is enough.
     */
    private function rewindToTheStateBeforeThePermission(): void
    {
        Permission::findByName(self::OPERATIONAL_NOTIFICATIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function reconcile(): void
    {
        $migration = require database_path('migrations/2026_10_14_090000_sync_role_permissions_from_catalog.php');

        $migration->up();
    }

    private function staffWithTheRoleAttached(): User
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'permission_migration_staff']);

        return $staff->fresh();
    }
}
