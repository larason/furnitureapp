<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Authorization\Authorization;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\PermissionName;
use App\Support\RoleName;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_exactly_the_three_v1_roles(): void
    {
        $this->seed(RbacSeeder::class);

        $roles = DB::table('roles')->orderBy('name')->get()->toArray();

        $this->assertSame(3, count($roles));
        $this->assertSame(['ADMIN', 'CUSTOMER', 'STAFF'], array_column($roles, 'name'));
        $this->assertSame(['web', 'web', 'web'], array_column($roles, 'guard_name'));
        $this->assertSame(null, DB::table('roles')->where('name', 'SUPER_ADMIN')->first());
    }

    public function test_duplicate_role_is_rejected(): void
    {
        $this->seed(RbacSeeder::class);

        $this->expectException(QueryException::class);
        DB::table('roles')->insert(['name' => 'CUSTOMER', 'guard_name' => 'web']);
    }

    public function test_unknown_role_is_never_effective(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::CUSTOMER);

        $this->assertFalse($user->hasRole('SUPER_ADMIN'));
        $this->assertFalse($user->hasRole('MANAGER'));
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::CUSTOMER);

        $user->fill(['name' => 'Updated', 'role' => 'ADMIN', 'is_admin' => true])->save();

        $fresh = $user->fresh();
        $this->assertSame('Updated', $fresh->name);
        $this->assertFalse($fresh->hasRole('ADMIN'));
        $this->assertCount(1, $fresh->roles);
    }

    public function test_all_v1_permissions_are_seeded_and_unique(): void
    {
        $this->seed(RbacSeeder::class);

        $names = array_column(DB::table('permissions')->orderBy('name')->get()->toArray(), 'name');
        $expected = array_map(fn (PermissionName $p) => $p->value, PermissionCatalog::all());
        sort($expected);

        $this->assertSame($expected, $names);
        $this->assertCount(20, $names);
        $this->assertCount(20, array_unique($names));
    }

    public function test_wildcard_permission_is_not_enabled(): void
    {
        $this->seed(RbacSeeder::class);

        $this->assertFalse(config('permission.enable_wildcard_permission'));
        $this->assertSame(null, DB::table('permissions')->where('name', '*')->first());
    }

    public function test_customer_has_no_staff_operational_permissions(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::CUSTOMER);

        foreach (PermissionCatalog::forRole(RoleName::STAFF) as $permission) {
            $this->assertFalse($user->checkPermissionTo($permission->value), "CUSTOMER must not have {$permission->value}");
        }
    }

    public function test_staff_has_agreed_operational_capabilities(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::STAFF);

        foreach (PermissionCatalog::forRole(RoleName::STAFF) as $permission) {
            $this->assertTrue($user->checkPermissionTo($permission->value), "STAFF must have {$permission->value}");
        }
    }

    public function test_staff_cannot_perform_admin_only_operations(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::STAFF);

        $this->assertFalse($user->checkPermissionTo(PermissionName::STAFF_APPROVE->value));
        $this->assertFalse($user->checkPermissionTo(PermissionName::STAFF_MANAGE->value));
        $this->assertFalse($user->checkPermissionTo(PermissionName::USERS_MANAGE_AUTHORIZED->value));
        $this->assertFalse($user->checkPermissionTo(PermissionName::AUDIT_VIEW->value));
    }

    public function test_admin_without_staff_approval_permission_is_denied(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_no_approval']);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_target']);
        Role::findByName(RoleName::ADMIN->value)->revokePermissionTo(PermissionName::STAFF_APPROVE->value);
        $this->configureClerk($admin);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson("/api/v1/admin/staff/{$staff->id}/approve")
            ->assertForbidden();
    }

    public function test_admin_with_staff_approval_permission_reaches_approval_action(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_with_approval']);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_target']);
        $admin->givePermissionTo(PermissionName::STAFF_APPROVE->value);
        $this->configureClerk($admin);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson("/api/v1/admin/staff/{$staff->id}/approve")
            ->assertStatus(501);
    }

    public function test_staff_without_order_completion_permission_is_denied(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_no_complete']);
        Role::findByName(RoleName::STAFF->value)->revokePermissionTo(PermissionName::ORDERS_COMPLETE->value);
        $this->configureClerk($staff);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson('/api/v1/orders/OD-1/complete')
            ->assertForbidden();
    }

    public function test_staff_without_delivery_fee_permission_is_denied(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_no_fee']);
        Role::findByName(RoleName::STAFF->value)->revokePermissionTo(PermissionName::ORDERS_SET_DELIVERY_FEE->value);
        $this->configureClerk($staff);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson('/api/v1/orders/OD-1/delivery-fee', [])
            ->assertForbidden();
    }

    public function test_staff_with_delivery_fee_permission_reaches_delivery_fee_action(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_fee']);
        $this->configureClerk($staff);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson('/api/v1/orders/OD-1/delivery-fee', [])
            ->assertUnprocessable();
    }

    public function test_staff_with_direct_staff_approval_permission_is_denied(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_approval']);
        $staff->givePermissionTo(PermissionName::STAFF_APPROVE->value);
        $this->configureClerk($staff);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson('/api/v1/admin/staff/'.$staff->id.'/approve')
            ->assertForbidden();
    }

    public function test_active_staff_with_catalog_permission_reaches_catalog_write(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_catalog']);
        $this->configureClerk($staff);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson('/api/v1/products', [])
            ->assertUnprocessable();
    }

    public function test_active_staff_without_catalog_permission_is_denied(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_no_catalog']);
        Role::findByName(RoleName::STAFF->value)->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $this->configureClerk($staff);

        $this->withHeaders(['Authorization' => 'Bearer session-token'])
            ->postJson('/api/v1/products', [])
            ->assertForbidden();
    }

    public function test_admin_has_explicit_administrative_capabilities(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::ADMIN);

        foreach (PermissionCatalog::all() as $permission) {
            $this->assertTrue($user->checkPermissionTo($permission->value), "ADMIN must have {$permission->value}");
        }
    }

    public function test_deny_by_default(): void
    {
        $this->seed(RbacSeeder::class);

        $roleless = $this->createUser();

        $this->assertFalse(app(Authorization::class)->allows(null, PermissionName::PRODUCTS_VIEW));
        $this->assertFalse(app(Authorization::class)->allows($roleless, PermissionName::PRODUCTS_VIEW));
        $this->assertFalse($roleless->checkPermissionTo(PermissionName::PRODUCTS_VIEW->value));
    }

    public function test_role_alone_is_not_sufficient(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::CUSTOMER);

        $this->assertTrue($user->hasRole(RoleName::CUSTOMER->value));
        $this->assertFalse(app(Authorization::class)->allows($user, PermissionName::ORDERS_SHIP));
    }

    public function test_authorization_is_identity_based(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = $this->userWithRole(RoleName::STAFF);
        $customer = $this->userWithRole(RoleName::CUSTOMER);

        $this->assertTrue(app(Authorization::class)->allows($staff, PermissionName::ORDERS_SHIP));
        $this->assertFalse(app(Authorization::class)->allows($customer, PermissionName::ORDERS_SHIP));
    }

    public function test_permissions_cannot_be_mass_assigned(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->userWithRole(RoleName::CUSTOMER);

        $user->fill(['name' => 'Tampered', 'permissions' => ['*'], 'role' => 'ADMIN'])->save();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->checkPermissionTo(PermissionName::PRODUCTS_MANAGE->value));
        $this->assertFalse($fresh->hasRole(RoleName::ADMIN->value));
    }

    public function test_seed_is_deterministic(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(RbacSeeder::class);

        $expectedMappings = count(PermissionCatalog::forRole(RoleName::STAFF)) + count(PermissionCatalog::forRole(RoleName::ADMIN));

        $this->assertCount(3, DB::table('roles')->get());
        $this->assertCount(20, DB::table('permissions')->get());
        $this->assertCount($expectedMappings, DB::table('role_has_permissions')->get());
    }

    private function userWithRole(RoleName $role): User
    {
        $user = $this->createUser();
        $user->assignRole($role->value);

        return $user->fresh();
    }

    private function createUser(): User
    {
        $user = new User(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail()]);
        $user->password = 'secret';
        $user->save();

        return $user;
    }

    private function configureClerk(User $user): void
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            $user->clerk_user_id,
            'sess_test',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);
    }
}
