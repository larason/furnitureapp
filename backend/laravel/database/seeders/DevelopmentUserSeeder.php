<?php

namespace Database\Seeders;

use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * LOCAL DEVELOPMENT ONLY. Never run against production.
 *
 * Creates one customer, one staff, and one admin account sharing the password
 * from the SEED_DEMO_PASSWORD environment variable (see .env.example).
 * Repeatable: existing emails are reused and roles/profiles are ensured, but
 * an existing account's credentials are never overwritten — rerunning must not
 * reset a changed password back to the shared demo value.
 */
class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new DevelopmentSeederException('DevelopmentUserSeeder may run only in local or testing environments.');
        }

        if (app()->environment('local') && (! is_string(config('demo.user_password')) || config('demo.user_password') === '')) {
            throw new DevelopmentSeederException('SEED_DEMO_PASSWORD must be set before running DevelopmentUserSeeder.');
        }

        $this->seedUser('Amina Customer', 'customer@example.com', RoleName::CUSTOMER, false);
        $this->seedUser('Baraka Staff', 'staff@example.com', RoleName::STAFF, true);
        $this->seedUser('Zawadi Admin', 'admin@example.com', RoleName::ADMIN, true);
    }

    private function seedUser(string $name, string $email, RoleName $role, bool $isStaff): void
    {
        $password = (string) config('demo.user_password');

        if (app()->environment('testing') && ! $password) {
            $password = 'testing-only-password';
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => '+255700000001',
                'email_verified_at' => now(),
                'account_state' => $isStaff ? 'ACTIVE' : null,
            ]
        );

        if ($user->wasRecentlyCreated) {
            // `password` is guarded from mass assignment; apply the demo
            // credential once on creation and never on later reruns.
            $user->forceFill(['password' => $password])->save();
        }

        if ($isStaff && $user->account_state === null) {
            $user->forceFill(['account_state' => 'ACTIVE'])->save();
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
