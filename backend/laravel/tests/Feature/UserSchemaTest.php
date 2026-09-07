<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_has_required_identity_fields(): void
    {
        $user = $this->createUser([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '255700000000',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '255700000000',
        ]);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function test_email_is_unique(): void
    {
        $this->createUser(['email' => 'dup@example.com']);

        $this->expectException(QueryException::class);
        $this->createUser(['email' => 'dup@example.com']);
    }

    public function test_phone_is_nullable(): void
    {
        $user = $this->createUser(['email' => 'nophone@example.com']);

        $this->assertNull($user->phone);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => null]);
    }

    public function test_password_stores_secure_hash(): void
    {
        $user = $this->createUser(['email' => 'hash@example.com'], 'secret');

        $this->assertTrue(Hash::check('secret', $user->password));
        $this->assertNotSame('secret', $user->password);
    }

    public function test_account_state_is_server_controlled_not_fillable(): void
    {
        $user = $this->createUser(['email' => 'controlled@example.com', 'account_state' => 'ACTIVE']);

        $this->assertNull($user->account_state, 'account_state must not be mass-assigned from input');
    }

    public function test_profile_relationships_are_one_to_one_and_optional(): void
    {
        $user = $this->createUser(['email' => 'rel@example.com']);

        $this->assertNull($user->customerProfile);
        $this->assertNull($user->staffProfile);

        $profile = CustomerProfile::create(['user_id' => $user->id]);
        $this->assertTrue($user->fresh()->customerProfile->is($profile));
        $this->assertTrue($profile->user->is($user));

        $staffProfile = StaffProfile::create(['user_id' => $user->id]);
        $this->assertTrue($user->fresh()->staffProfile->is($staffProfile));
        $this->assertTrue($staffProfile->user->is($user));
    }

    public function test_duplicate_customer_profile_rejected(): void
    {
        $user = $this->createUser(['email' => 'dupp@example.com']);
        CustomerProfile::create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        CustomerProfile::create(['user_id' => $user->id]);
    }

    public function test_duplicate_staff_profile_rejected(): void
    {
        $user = $this->createUser(['email' => 'dups@example.com']);
        StaffProfile::create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        StaffProfile::create(['user_id' => $user->id]);
    }

    public function test_profile_cannot_reference_nonexistent_user(): void
    {
        $this->expectException(QueryException::class);
        CustomerProfile::create(['user_id' => 999999]);
    }

    public function test_serialization_does_not_expose_credentials(): void
    {
        $user = $this->createUser(['email' => 'secret@example.com'], 'hidden');

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
    }

    public function test_password_cannot_be_changed_via_mass_assignment(): void
    {
        $user = $this->createUser(['email' => 'secure@example.com'], 'original');

        $user->fill(['name' => 'Updated', 'password' => 'compromised'])->save();

        $fresh = $user->fresh();
        $this->assertSame('Updated', $fresh->name);
        $this->assertTrue(Hash::check('original', $fresh->password));
        $this->assertFalse(Hash::check('compromised', $fresh->password));
    }

    private function createUser(array $attributes = [], string $password = 'password'): User
    {
        $user = new User(array_merge(['name' => 'Test User'], $attributes));
        $user->password = $password;
        $user->save();

        return $user;
    }
}
