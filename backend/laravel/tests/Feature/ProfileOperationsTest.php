<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Exceptions\MigrationPreconditionException;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            ->assertJsonPath('data.role', 'CUSTOMER');
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
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            'user_123',
            'sess_123',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return $this->withHeaders(['Authorization' => 'Bearer session-token']);
    }
}
