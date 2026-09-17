<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->password = static::$password ??= Hash::make('password');
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $attributes['phone'] ?? '+2557'.fake()->numerify('########'),
        ])->afterCreating(function (User $user): void {
            $this->assignRole($user, RoleName::CUSTOMER);
            CustomerProfile::firstOrCreate(['user_id' => $user->id]);
        });
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $attributes['phone'] ?? '+2557'.fake()->numerify('########'),
            'account_state' => 'ACTIVE',
        ])->afterCreating(function (User $user): void {
            $this->assignRole($user, RoleName::STAFF);
            StaffProfile::firstOrCreate(['user_id' => $user->id]);
        });
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $attributes['phone'] ?? '+2557'.fake()->numerify('########'),
            'account_state' => 'ACTIVE',
        ])->afterCreating(function (User $user): void {
            $this->assignRole($user, RoleName::ADMIN);
            StaffProfile::firstOrCreate(['user_id' => $user->id]);
        });
    }

    private function assignRole(User $user, RoleName $role): void
    {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => config('auth.defaults.guard')]);
        $user->assignRole($role->value);
    }
}
