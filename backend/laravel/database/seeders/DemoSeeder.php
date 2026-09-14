<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

/**
 * LOCAL DEVELOPMENT ONLY. Never run against production.
 *
 * Populates a fresh database with the full demo dataset in dependency order:
 * reference data → users → catalogue → commerce. Invoke explicitly:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Reference seeders are idempotent; demo seeders assume a fresh database.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('DemoSeeder cannot run in production.');
        }

        app(RbacSeeder::class)->run();

        $this->call([
            CategorySeeder::class,
            DevelopmentUserSeeder::class,
            CatalogSeeder::class,
            CommerceDemoSeeder::class,
        ]);
    }
}
