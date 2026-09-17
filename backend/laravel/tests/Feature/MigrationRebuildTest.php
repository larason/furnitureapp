<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MigrationRebuildTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function domainTables(): array
    {
        return [
            'users',
            'customer_profiles',
            'staff_profiles',
            'roles',
            'permissions',
            'categories',
            'category_recommendations',
            'products',
            'product_variants',
            'product_images',
            'product_stocks',
            'carts',
            'cart_items',
            'orders',
            'order_items',
            'order_status_history',
            'payments',
            'payment_webhook_events',
            'deliveries',
            'furniture_requests',
            'enquiries',
            'notifications',
        ];
    }

    public function test_every_domain_table_exists_with_primary_key(): void
    {
        foreach ($this->domainTables() as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} must exist.");
            $this->assertTrue(Schema::hasColumn($table, 'id'), "Table {$table} must have an id column.");

            $primaries = array_values(array_filter(
                Schema::getIndexes($table),
                static fn (array $index): bool => ! empty($index['primary'])
            ));

            $this->assertCount(1, $primaries, "Table {$table} must carry exactly one primary key.");
            $this->assertSame(['id'], $primaries[0]['columns'], "Table {$table} must use id as its primary key.");
        }
    }

    public function test_review_corrections_are_present_in_schema(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('enquiries', 'category'));
        $this->assertTrue(Schema::hasColumn('carts', 'active_user_guard'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'is_default_guard'));
        $this->assertTrue(Schema::hasColumn('product_images', 'is_primary_guard'));
        $this->assertTrue(Schema::hasColumn('cart_items', 'null_variant_guard'));
    }

    public function test_soft_deletes_exist_only_on_products(): void
    {
        foreach (['orders', 'payments', 'carts', 'notifications', 'deliveries', 'furniture_requests', 'enquiries', 'product_variants'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'), "Table {$table} must not use soft deletes.");
        }
    }

    public function test_history_table_has_no_updated_at(): void
    {
        $this->assertTrue(Schema::hasColumn('order_status_history', 'created_at'));
        $this->assertTrue(Schema::hasColumn('order_status_history', 'occurred_at'));
        $this->assertFalse(Schema::hasColumn('order_status_history', 'updated_at'));
    }

    public function test_every_migration_file_has_been_applied(): void
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        $applied = DB::table('migrations')->pluck('migration')->all();

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $name = basename((string) $file, '.php');
            $this->assertContains($name, $applied, "Migration {$name} must be applied.");
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function foreignKeyPolicyProvider(): array
    {
        return [
            ['customer_profiles', 'user_id', 'users', 'id', 'cascade'],
            ['staff_profiles', 'user_id', 'users', 'id', 'cascade'],
            ['categories', 'parent_id', 'categories', 'id', 'set null'],
            ['category_recommendations', 'category_id', 'categories', 'id', 'cascade'],
            ['category_recommendations', 'recommended_category_id', 'categories', 'id', 'cascade'],
            ['products', 'category_id', 'categories', 'id', 'restrict'],
            ['product_variants', 'product_id', 'products', 'id', 'cascade'],
            ['product_images', 'product_id', 'products', 'id', 'cascade'],
            ['product_images', 'product_variant_id', 'product_variants', 'id', 'set null'],
            ['product_stocks', 'product_variant_id', 'product_variants', 'id', 'cascade'],
            ['carts', 'user_id', 'users', 'id', 'restrict'],
            ['cart_items', 'cart_id', 'carts', 'id', 'cascade'],
            ['cart_items', 'product_id', 'products', 'id', 'restrict'],
            ['cart_items', 'variant_id', 'product_variants', 'id', 'restrict'],
            ['orders', 'customer_id', 'users', 'id', 'restrict'],
            ['order_items', 'order_id', 'orders', 'id', 'cascade'],
            ['order_items', 'product_id', 'products', 'id', 'set null'],
            ['order_items', 'variant_id', 'product_variants', 'id', 'set null'],
            ['order_status_history', 'order_id', 'orders', 'id', 'cascade'],
            ['order_status_history', 'actor_id', 'users', 'id', 'set null'],
            ['payments', 'order_id', 'orders', 'id', 'restrict'],
            ['payment_webhook_events', 'payment_id', 'payments', 'id', 'set null'],
            ['deliveries', 'order_id', 'orders', 'id', 'restrict'],
            ['furniture_requests', 'user_id', 'users', 'id', 'set null'],
            ['furniture_requests', 'product_id', 'products', 'id', 'set null'],
            ['enquiries', 'user_id', 'users', 'id', 'set null'],
            ['enquiries', 'product_id', 'products', 'id', 'set null'],
            ['enquiries', 'order_id', 'orders', 'id', 'restrict'],
            ['notifications', 'recipient_user_id', 'users', 'id', 'restrict'],
        ];
    }

    #[DataProvider('foreignKeyPolicyProvider')]
    public function test_foreign_key_uses_approved_delete_action(string $table, string $column, string $referenceTable, string $referenceColumn, string $onDelete): void
    {
        $match = null;

        foreach (Schema::getForeignKeys($table) as $key) {
            if ($key['columns'] === [$column]) {
                $match = $key;
            }
        }

        $this->assertNotNull($match, "Foreign key on {$table}.{$column} must exist.");
        $this->assertSame($referenceTable, $match['foreign_table']);
        $this->assertSame([$referenceColumn], $match['foreign_columns']);

        $this->assertSame($onDelete, $this->normalizeAction($match['on_delete']));
    }

    /**
     * @return array<string, int>
     */
    private function expectedForeignKeyCounts(): array
    {
        return [
            'customer_profiles' => 1,
            'staff_profiles' => 1,
            'categories' => 1,
            'category_recommendations' => 2,
            'products' => 1,
            'product_variants' => 1,
            'product_images' => 2,
            'product_stocks' => 1,
            'carts' => 1,
            'cart_items' => 3,
            'orders' => 1,
            'order_items' => 3,
            'order_status_history' => 2,
            'payments' => 1,
            'payment_webhook_events' => 1,
            'deliveries' => 1,
            'furniture_requests' => 2,
            'enquiries' => 3,
            'notifications' => 1,
        ];
    }

    public function test_no_table_carries_unexpected_foreign_keys(): void
    {
        foreach ($this->expectedForeignKeyCounts() as $table => $count) {
            $this->assertCount($count, Schema::getForeignKeys($table), "Table {$table} must carry exactly {$count} foreign keys.");
        }
    }

    private function normalizeAction(mixed $action): string
    {
        return strtolower(str_replace('_', ' ', (string) $action));
    }
}
