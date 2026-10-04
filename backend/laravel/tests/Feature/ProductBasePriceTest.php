<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductBasePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_price_uses_persisted_base_price_not_variant_price(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price_amount' => 125000000,
            'price_currency' => 'TZS',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 150000000,
            'price_currency' => 'TZS',
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.price.amount', 125000000)
            ->assertJsonPath('data.price.currency', 'TZS');
    }

    public function test_price_migration_backfills_the_previous_minimum_active_variant_price(): void
    {
        $migration = require database_path('migrations/2026_10_04_130000_add_base_price_to_products_table.php');
        $migration->down();

        $category = Category::factory()->create();
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Legacy Product',
            'slug' => 'legacy-product',
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 300, 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 100, 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 50, 'is_active' => false]);

        $migration->up();

        $this->assertSame(100, $product->fresh()->price_amount);
        $this->assertSame('TZS', $product->fresh()->price_currency);
    }

    public function test_price_migration_refuses_rollback_when_product_prices_exist(): void
    {
        $product = Product::factory()->create(['price_amount' => 999]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 100]);
        $migration = require database_path('migrations/2026_10_04_130000_add_base_price_to_products_table.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be rolled back while Products exist');

        $migration->down();
    }

    public function test_price_migration_rejects_non_tzs_selected_variant_before_schema_change(): void
    {
        $migration = require database_path('migrations/2026_10_04_130000_add_base_price_to_products_table.php');
        $migration->down();
        $category = Category::factory()->create();
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Legacy Foreign Currency Product',
            'slug' => 'legacy-foreign-currency-product',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 100,
            'price_currency' => 'USD',
            'is_active' => true,
        ]);

        try {
            $migration->up();
            $this->fail('Expected a non-TZS canonical Variant price to block migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('currency must be TZS', $exception->getMessage());
            $this->assertFalse(Schema::hasColumn('products', 'price_amount'));
        }
    }
}
