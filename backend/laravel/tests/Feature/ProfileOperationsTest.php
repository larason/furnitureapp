<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Exceptions\MigrationPreconditionException;
use App\Models\Cart;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleName;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_me_returns_private_current_profile(): void
    {
        $user = $this->customer();

        $response = $this->bearer()->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonPath('data.name', 'Profile Customer')
            ->assertJsonPath('data.email', 'profile@example.com')
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.role', 'CUSTOMER')
            ->assertJsonPath('data.account_state', null)
            ->assertJsonPath('data.permissions', []);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('clerk_user_id', $response->json('data'));
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    public function test_patch_me_updates_name_and_phone_without_clerk_call(): void
    {
        $user = $this->customer(['phone' => null]);

        $response = $this->bearer()->patchJson('/api/v1/me', [
            'name' => 'Updated Customer',
            'phone' => '+255700000001',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Customer')
            ->assertJsonPath('data.phone', '+255700000001');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Customer',
            'phone' => '+255700000001',
            'email' => 'profile@example.com',
        ]);
    }

    public function test_patch_me_is_partial_and_can_clear_phone(): void
    {
        $user = $this->customer(['phone' => '+255700000001']);

        $this->bearer()->patchJson('/api/v1/me', ['name' => 'Only Name'])->assertOk();
        $this->assertSame('+255700000001', $user->fresh()->phone);

        $this->bearer()->patchJson('/api/v1/me', ['phone' => null])->assertOk();
        $this->assertNull($user->fresh()->phone);
    }

    public function test_identical_patch_does_not_update_timestamp(): void
    {
        $user = $this->customer();
        $updatedAt = $user->updated_at;

        $this->travel(1)->seconds();
        $this->bearer()->patchJson('/api/v1/me', ['name' => $user->name])->assertOk();

        $this->assertTrue($user->fresh()->updated_at->equalTo($updatedAt));
    }

    public function test_patch_me_rejects_server_controlled_fields(): void
    {
        $user = $this->customer();
        $response = $this->bearer()->patchJson('/api/v1/me', [
            'role' => 'ADMIN',
            'permissions' => ['*'],
            'email' => 'attacker@example.com',
            'email_verified' => true,
            'clerk_user_id' => 'user_other',
            'account_state' => 'ACTIVE',
            'password' => 'secret',
        ]);

        $response->assertStatus(422);
        $fresh = $user->fresh();
        $this->assertSame('CUSTOMER', $fresh->getRoleNames()->first());
        $this->assertSame('profile@example.com', $fresh->email);
        $this->assertSame('user_123', $fresh->clerk_user_id);
        $this->assertNull($fresh->account_state);
        $this->assertNotNull($fresh->getRawOriginal('password'));
    }

    public function test_patch_me_rejects_empty_and_invalid_profile_values(): void
    {
        $this->customer();

        $this->bearer()->patchJson('/api/v1/me', [])->assertStatus(422);
        $this->bearer()->patchJson('/api/v1/me', ['name' => '   '])->assertStatus(422);
        $this->bearer()->patchJson('/api/v1/me', ['phone' => 'not-a-phone'])->assertStatus(422);
    }

    public function test_me_requires_clerk_bearer_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_inactive_customer_cannot_access_authenticated_routes(): void
    {
        $this->customer(['account_state' => 'SUSPENDED']);

        $this->bearer()->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_me_serializes_active_staff_and_admin_effective_permissions_deterministically(): void
    {
        $this->seed(RbacSeeder::class);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_profile']);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_profile']);

        $staffResponse = $this->bearerFor('staff_profile')->getJson('/api/v1/me');
        $adminResponse = $this->bearerFor('admin_profile')->getJson('/api/v1/me');

        $staffResponse->assertOk()
            ->assertJsonPath('data.role', RoleName::STAFF->value)
            ->assertJsonPath('data.account_state', 'ACTIVE')
            ->assertJsonPath('data.permissions', $this->permissionsFor(RoleName::STAFF));
        $adminResponse->assertOk()
            ->assertJsonPath('data.role', RoleName::ADMIN->value)
            ->assertJsonPath('data.account_state', 'ACTIVE')
            ->assertJsonPath('data.permissions', $this->permissionsFor(RoleName::ADMIN));
    }

    public function test_suspended_staff_is_rejected_before_identity_serialization(): void
    {
        $staff = User::factory()->staff()->create([
            'clerk_user_id' => 'suspended_staff_profile',
            'account_state' => 'SUSPENDED',
        ]);

        $this->bearerFor('suspended_staff_profile')->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        $this->assertSame('SUSPENDED', $staff->fresh()->account_state);
    }

    public function test_active_staff_with_no_effective_permissions_serializes_an_empty_permission_list(): void
    {
        $this->seed(RbacSeeder::class);
        $staffRole = Role::query()
            ->where('name', RoleName::STAFF->value)
            ->where('guard_name', config('auth.defaults.guard'))
            ->firstOrFail();
        $staffRole->syncPermissions([]);
        User::factory()->staff()->create(['clerk_user_id' => 'staff_without_permissions']);

        $this->bearerFor('staff_without_permissions')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', RoleName::STAFF->value)
            ->assertJsonPath('data.account_state', 'ACTIVE')
            ->assertJsonPath('data.permissions', []);
    }

    public function test_missing_or_ambiguous_local_roles_are_rejected(): void
    {
        $this->seed(RbacSeeder::class);
        $missingRole = User::factory()->create(['clerk_user_id' => 'missing_profile_role']);
        $ambiguousRole = User::factory()->create(['clerk_user_id' => 'ambiguous_profile_role']);
        $ambiguousRole->assignRole(RoleName::STAFF->value, RoleName::ADMIN->value);

        $this->bearerFor('missing_profile_role')->getJson('/api/v1/me')->assertForbidden();
        $this->bearerFor('ambiguous_profile_role')->getJson('/api/v1/me')->assertForbidden();
        $this->assertCount(0, $missingRole->getRoleNames());
    }

    public function test_ineligible_role_is_rejected_before_a_profile_write(): void
    {
        $this->seed(RbacSeeder::class);
        $ambiguousRole = User::factory()->create([
            'clerk_user_id' => 'ambiguous_patch_role',
            'name' => 'Original Name',
            'phone' => null,
        ]);
        $ambiguousRole->assignRole(RoleName::STAFF->value, RoleName::ADMIN->value);

        $this->bearerFor('ambiguous_patch_role')
            ->patchJson('/api/v1/me', ['name' => 'Mutated Name', 'phone' => '+255700000009'])
            ->assertForbidden();

        $fresh = $ambiguousRole->fresh();
        $this->assertSame('Original Name', $fresh->name, 'An ineligible role must not persist a profile write.');
        $this->assertNull($fresh->phone);
    }

    public function test_missing_local_role_is_rejected_before_a_profile_write(): void
    {
        $this->seed(RbacSeeder::class);
        $missingRole = User::factory()->create([
            'clerk_user_id' => 'missing_patch_role',
            'name' => 'Original Name',
        ]);

        $this->bearerFor('missing_patch_role')
            ->patchJson('/api/v1/me', ['name' => 'Mutated Name'])
            ->assertForbidden();

        $this->assertSame('Original Name', $missingRole->fresh()->name);
    }

    public function test_cart_user_foreign_key_is_restrictive(): void
    {
        $foreignKey = collect(Schema::getForeignKeys('carts'))
            ->first(fn (array $key): bool => $key['columns'] === ['user_id']);

        $this->assertNotNull($foreignKey);
        $this->assertSame('restrict', strtolower((string) $foreignKey['on_delete']));
    }

    public function test_user_with_cart_cannot_be_hard_deleted(): void
    {
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id]);

        try {
            $user->delete();
            $this->fail('Expected cart ownership to restrict user deletion.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas('carts', ['id' => $cart->id, 'user_id' => $user->id]);
        }
    }

    public function test_name_nullability_rollback_rejects_null_names_without_backfill(): void
    {
        DB::table('users')->insert([
            'name' => null,
            'email' => 'rollback@example.com',
            'email_verified_at' => null,
            'password' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_17_110000_make_user_name_nullable.php');

        $this->expectException(MigrationPreconditionException::class);
        $this->expectExceptionMessage('no approved backfill value exists');

        try {
            $migration->down();
        } finally {
            $this->assertTrue(Schema::hasColumn('users', 'name'));
        }
    }

    private function customer(array $attributes = []): User
    {
        $user = User::factory()->customer()->create(array_merge([
            'clerk_user_id' => 'user_123',
            'name' => 'Profile Customer',
            'email' => 'profile@example.com',
        ], $attributes));

        $user->forceFill(['phone' => $attributes['phone'] ?? null])->save();

        return $user->refresh();
    }

    private function bearer(): self
    {
        return $this->bearerFor('user_123');
    }

    private function bearerFor(string $clerkUserId): self
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            $clerkUserId,
            'sess_123',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return $this->withHeaders(['Authorization' => 'Bearer session-token']);
    }

    /** @return list<string> */
    private function permissionsFor(RoleName $role): array
    {
        return collect(PermissionCatalog::forRole($role))
            ->map(static fn ($permission): string => $permission->value)
            ->sort()
            ->values()
            ->all();
    }
}
