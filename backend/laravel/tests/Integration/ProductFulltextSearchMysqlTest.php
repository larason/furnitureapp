<?php

namespace Tests\Integration;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductIdentifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 5.5 / Group E closure gate: native FULLTEXT search on MySQL/MariaDB.
 *
 * Not part of the default suite. Run explicitly against the disposable
 * database, e.g.:
 *
 *   FULLTEXT_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/ProductFulltextSearchMysqlTest.php
 */
class ProductFulltextSearchMysqlTest extends TestCase
{
    private const CONNECTION = 'mysql_fulltext';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private const INDEX = 'products_name_description_fulltext';

    private string $previousDefaultConnection = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');

        $database = (string) getenv('FULLTEXT_MYSQL_TEST_DATABASE');

        if ($database !== self::DISPOSABLE_DATABASE) {
            $this->markTestSkipped('requires disposable MySQL/MariaDB integration database (set FULLTEXT_MYSQL_TEST_DATABASE='.self::DISPOSABLE_DATABASE.').');
        }

        if (app()->environment('production')) {
            $this->fail('Refusing to run destructive FULLTEXT tests in production.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(
            config('database.connections.mysql'),
            ['database' => $database],
        )]);
        config(['database.default' => self::CONNECTION]);

        DB::purge(self::CONNECTION);
        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        config(['database.default' => $this->previousDefaultConnection]);

        parent::tearDown();
    }

    public function test_fulltext_index_exists_on_products(): void
    {
        $index = DB::connection(self::CONNECTION)->selectOne(
            'select index_name, index_type from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? and index_type = ?',
            [self::DISPOSABLE_DATABASE, 'products', self::INDEX, 'FULLTEXT'],
        );

        $this->assertNotNull($index, 'The FULLTEXT index is missing on products.');
    }

    public function test_fulltext_matches_name_and_description_and_excludes_non_matches(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $named = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Mahogany Dining Table',
            'slug' => 'mahogany-dining-table',
            'description' => 'Sturdy assembled piece',
        ]);
        $described = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Simple Desk',
            'slug' => 'simple-desk',
            'description' => 'Crafted from walnut hardwood',
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Plain Stool',
            'slug' => 'plain-stool',
            'description' => 'Basic seating',
        ]);

        $this->getJson('/api/v1/products?search=mahogany')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($named));

        $this->getJson('/api/v1/products?search=walnut')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($described));

        $this->getJson('/api/v1/products?search=pineapple')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_fulltext_respects_public_visibility(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->draft()->create([
            'category_id' => $category->id,
            'name' => 'Mahogany Draft Sofa',
            'slug' => 'mahogany-draft-sofa',
        ]);
        $published = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Mahogany Accent Chair',
            'slug' => 'mahogany-accent-chair',
        ]);

        $this->getJson('/api/v1/products?search=mahogany')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($published))
            ->assertJsonMissing(['slug' => 'mahogany-draft-sofa']);
    }
}
