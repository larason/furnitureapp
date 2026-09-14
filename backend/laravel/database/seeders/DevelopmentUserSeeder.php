<?php

namespace Database\Seeders;

use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * LOCAL DEVELOPMENT ONLY. Never run against production.
 *
 * Creates one customer, one staff, and one admin account sharing the password
 * from the SEED_DEMO_PASSWORD environment variable (see .env.example).
 * When unset, a random password is generated and printed once to the console.
 * Repeatable: existing emails are reused and roles/profiles are ensured.
 */
class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser('Amina Customer', 'customer@example.com', RoleName::CUSTOMER, false);
        $this->seedUser('Baraka Staff', 'staff@example.com', RoleName::STAFF, true);
        $this->seedUser('Zawadi Admin', 'admin@example.com', RoleName::ADMIN, true);
    }

    private function seedUser(string $name, string $email, RoleName $role, bool $isStaff): void
    {
        $configured = config('demo.user_password');
        $generated = ! is_string($configured) || $configured === '';
        $password = $generated ? Str::random(16) : $configured;

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => '+255700000001',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if ($generated && $user->wasRecentlyCreated) {
            $this->command->info("Demo user {$email} created with generated password: {$password}");
        }

        Role::firstOrCreate(['name' => $role->value, 'guard_name' => config('auth.defaults.guard')]);

        if (! $user->hasRole($role->value)) {
            $user->assignRole($role->value);
        }

        if ($isStaff) {
            StaffProfile::firstOrCreate(['user_id' => $user->id]);
        } else {
            CustomerProfile::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
