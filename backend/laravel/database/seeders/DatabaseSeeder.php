<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed production-safe reference data only (roles, permissions, taxonomy).
     *
     * Demo users and commerce data are intentionally excluded: run the
     * local-only DemoSeeder explicitly (`php artisan db:seed --class=DemoSeeder`).
     */
    public function run(): void
    {
        app(RbacSeeder::class)->run();

        $this->call(CategorySeeder::class);
    }
}
