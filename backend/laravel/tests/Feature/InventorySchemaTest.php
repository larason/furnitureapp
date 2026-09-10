<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_stocks_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('product_stocks'));
        $this->assertTrue(Schema::hasColumns('product_stocks', [
            'id',
            'product_variant_id',
            'warehouse_location',
            'quantity',
            'reserved_quantity',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_stock_belongs_to_a_product_variant(): void
    {
        $variant = $this->createVariant();
        $stock = ProductStock::factory()->forVariant($variant)->create();

        $this->assertSame($variant->id, $stock->product_variant_id);
        $this->assertTrue($stock->productVariant->is($variant));
    }

    public function test_variant_returns_its_stocks(): void
    {
        $variant = $this->createVariant();
        $main = ProductStock::factory()->forVariant($variant)->atLocation('main')->create();
        $retail = ProductStock::factory()->forVariant($variant)->atLocation('retail-store')->create();

        $stocks = $variant->fresh()->stocks;

        $this->assertCount(2, $stocks);
        $this->assertTrue($stocks->contains($main));
        $this->assertTrue($stocks->contains($retail));
    }

    public function test_variant_can_have_multiple_locations(): void
    {
        $variant = $this->createVariant();

        ProductStock::factory()->forVariant($variant)->atLocation('main')->create();
        ProductStock::factory()->forVariant($variant)->atLocation('dar-es-salaam')->create();
        ProductStock::factory()->forVariant($variant)->atLocation('arusha-store')->create();

        $this->assertSame(3, ProductStock::where('product_variant_id', $variant->id)->count());
        $locations = ProductStock::where('product_variant_id', $variant->id)
            ->pluck('warehouse_location')
            ->all();
        $this->assertContains('main', $locations);
        $this->assertContains('dar-es-salaam', $locations);
        $this->assertContains('arusha-store', $locations);
    }

    public function test_invalid_product_variant_id_is_rejected_by_foreign_key(): void
    {
        $this->expectException(QueryException::class);
        ProductStock::query()->create([
            'product_variant_id' => 999999,
            'warehouse_location' => 'main',
        ]);
    }

    public function test_deleting_variant_cascades_to_stock_rows(): void
    {
        $variant = $this->createVariant();
        ProductStock::factory()->forVariant($variant)->atLocation('main')->create();
        ProductStock::factory()->forVariant($variant)->atLocation('retail-store')->create();

        $this->assertSame(2, ProductStock::count());

        $variant->delete();

        $this->assertSame(0, ProductStock::count());
        $this->assertSame(0, DB::table('product_stocks')->count());
    }

    public function test_duplicate_variant_location_combination_is_rejected(): void
    {
        $variant = $this->createVariant();
        ProductStock::factory()->forVariant($variant)->atLocation('main')->create();

        $this->expectException(QueryException::class);
        ProductStock::factory()->forVariant($variant)->atLocation('main')->create();
    }

    public function test_same_variant_with_different_locations_can_coexist(): void
    {
        $variant = $this->createVariant();
        $first = ProductStock::factory()->forVariant($variant)->atLocation('main')->create();
        $second = ProductStock::factory()->forVariant($variant)->atLocation('retail-store')->create();

        $this->assertDatabaseHas('product_stocks', [
            'id' => $first->id,
            'warehouse_location' => 'main',
        ]);
        $this->assertDatabaseHas('product_stocks', [
            'id' => $second->id,
            'warehouse_location' => 'retail-store',
        ]);
    }

    public function test_quantity_and_reserved_quantity_default_to_zero(): void
    {
        $variant = $this->createVariant();
        $stock = ProductStock::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_location' => 'main',
        ])->fresh();

        $this->assertSame(0, $stock->quantity);
        $this->assertSame(0, $stock->reserved_quantity);
    }

    public function test_quantities_are_non_negative_integers(): void
    {
        $variant = $this->createVariant();
        $stock = ProductStock::factory()->forVariant($variant)->create([
            'quantity' => 12,
            'reserved_quantity' => 3,
        ]);

        $this->assertIsInt($stock->quantity);
        $this->assertIsInt($stock->reserved_quantity);
        $this->assertSame(12, $stock->quantity);
        $this->assertSame(3, $stock->reserved_quantity);
    }

    public function test_available_quantity_is_derived_not_persisted(): void
    {
        $variant = $this->createVariant();
        $stock = ProductStock::factory()->forVariant($variant)->create([
            'quantity' => 10,
            'reserved_quantity' => 4,
        ])->fresh();

        $this->assertSame(6, $stock->available_quantity);
        $this->assertFalse(Schema::hasColumn('product_stocks', 'available_quantity'));
        $this->assertFalse(in_array('available_quantity', Schema::getColumnListing('product_stocks'), true));
    }

    public function test_available_quantity_equals_quantity_when_nothing_reserved(): void
    {
        $variant = $this->createVariant();
        $stock = ProductStock::factory()->forVariant($variant)->create([
            'quantity' => 10,
            'reserved_quantity' => 0,
        ])->fresh();

        $this->assertSame(10, $stock->available_quantity);
    }

    public function test_reserved_quantity_exceeding_quantity_is_rejected_by_application(): void
    {
        $variant = $this->createVariant();
        $stock = ProductStock::factory()->forVariant($variant)->create([
            'quantity' => 10,
            'reserved_quantity' => 4,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Reserved quantity cannot exceed quantity.');

        $stock->reserved_quantity = 11;
        $stock->save();
    }

    public function test_reserved_quantity_exceeding_quantity_is_rejected_by_database(): void
    {
        $variant = $this->createVariant();

        $this->expectException(QueryException::class);

        DB::table('product_stocks')->insert([
            'product_variant_id' => $variant->id,
            'warehouse_location' => 'main',
            'quantity' => 10,
            'reserved_quantity' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_negative_quantity_is_rejected_by_database(): void
    {
        $variant = $this->createVariant();

        $this->expectException(QueryException::class);

        DB::table('product_stocks')->insert([
            'product_variant_id' => $variant->id,
            'warehouse_location' => 'main',
            'quantity' => -1,
            'reserved_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_inventory_is_not_stored_on_products_or_variants(): void
    {
        $this->assertFalse(Schema::hasColumn('products', 'quantity'));
        $this->assertFalse(Schema::hasColumn('products', 'reserved_quantity'));
        $this->assertFalse(Schema::hasColumn('products', 'available_quantity'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'quantity'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'reserved_quantity'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'available_quantity'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'total_quantity'));
        $this->assertFalse(Schema::hasColumn('products', 'warehouse_location'));
    }

    public function test_stock_table_does_not_contain_speculative_columns(): void
    {
        $columns = Schema::getColumnListing('product_stocks');

        $this->assertNotContains('available_quantity', $columns);
        $this->assertNotContains('low_stock_threshold', $columns);
        $this->assertNotContains('product_type', $columns);
        $this->assertNotContains('stock_indicator', $columns);
        $this->assertNotContains('availability', $columns);
        $this->assertNotContains('warehouse_name', $columns);
        $this->assertNotContains('product_id', $columns);
    }

    public function test_stock_unique_constraint_exists_for_variant_and_location(): void
    {
        $indexes = collect(DB::select('PRAGMA index_list(product_stocks)'))
            ->pluck('name')
            ->all();
        $this->assertContains('product_stock_variant_location_unique', $indexes);
    }

    private function createVariant(): ProductVariant
    {
        return ProductVariant::factory()->for($this->createProduct())->create();
    }

    private function createProduct(): Product
    {
        return Product::factory()->for($this->createCategory())->create();
    }

    private function createCategory(array $attributes = []): Category
    {
        $category = new Category([
            'name' => $attributes['name'] ?? 'Category',
            'slug' => $attributes['slug'] ?? 'cat-'.strtolower(Str::random(6)),
            'space_type' => $attributes['space_type'] ?? 'home',
            'display_order' => $attributes['display_order'] ?? 0,
        ]);
        $category->save();

        return $category->fresh();
    }
}
