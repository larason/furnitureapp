<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductVariantSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_variants_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('product_variants'));
    }

    public function test_variant_belongs_to_a_product(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-V1']);

        $this->assertSame($product->id, $variant->product_id);
        $this->assertTrue($variant->product->is($product));
    }

    public function test_product_returns_its_variants(): void
    {
        $product = $this->createProduct();
        $first = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-A', 'display_order' => 2]);
        $second = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-B', 'display_order' => 1]);

        $variants = $product->fresh()->variants;

        $this->assertCount(2, $variants);
        $this->assertTrue($variants->contains($first));
        $this->assertTrue($variants->contains($second));
    }

    public function test_variants_are_ordered_by_display_order(): void
    {
        $product = $this->createProduct();
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-A', 'display_order' => 9]);
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-B', 'display_order' => 1]);
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-C', 'display_order' => 5]);

        $ordered = $product->fresh()->variants->pluck('sku')->all();

        $this->assertSame(['SKU-B', 'SKU-C', 'SKU-A'], $ordered);
    }

    public function test_invalid_product_id_is_rejected_by_foreign_key(): void
    {
        $this->expectException(QueryException::class);
        ProductVariant::query()->create([
            'product_id' => 999999,
            'sku' => 'SKU-ORPHAN',
            'variant_name' => 'Orphan Variant',
            'price_amount' => 10000,
        ]);
    }

    public function test_deleting_a_product_cascades_to_variants(): void
    {
        $product = $this->createProduct();
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-A']);
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-B']);

        $product->forceDelete();

        $this->assertSame(0, ProductVariant::count());
        $this->assertSame(0, DB::table('product_variants')->count());
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $product = $this->createProduct();
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-SHARED']);

        $this->expectException(QueryException::class);
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-SHARED']);
    }

    public function test_sku_is_globally_unique_across_products(): void
    {
        $firstProduct = $this->createProduct();
        $secondProduct = $this->createProduct();
        ProductVariant::factory()->for($firstProduct)->create(['sku' => 'SKU-GLOBAL']);

        $this->expectException(QueryException::class);
        ProductVariant::factory()->for($secondProduct)->create(['sku' => 'SKU-GLOBAL']);
    }

    public function test_empty_variant_name_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A variant name is required.');
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-EMPTY-NAME', 'variant_name' => '']);
    }

    public function test_empty_sku_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A variant SKU is required.');
        ProductVariant::factory()->for($product)->create(['sku' => '']);
    }

    public function test_price_is_required_by_the_database(): void
    {
        $product = $this->createProduct();

        $this->expectException(QueryException::class);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-NO-PRICE',
            'variant_name' => 'No Price Variant',
        ]);
    }

    public function test_price_is_stored_as_integer_minor_units(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-PRICE',
            'price_amount' => 35000000,
            'price_currency' => 'TZS',
        ]);

        $this->assertSame(35000000, $variant->price_amount);
        $this->assertSame('TZS', $variant->price_currency);
        $this->assertSame(35000000, DB::table('product_variants')->where('id', $variant->id)->value('price_amount'));
        $this->assertIsInt($variant->price_amount);
    }

    public function test_price_currency_defaults_to_tzs(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-DEFAULT-CURRENCY',
            'price_amount' => 50000,
        ])->fresh();

        $this->assertSame('TZS', $variant->price_currency);
    }

    public function test_compare_at_price_is_optional(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-NO-COMPARE',
            'compare_at_price_amount' => null,
            'compare_at_price_currency' => null,
        ]);

        $this->assertNull($variant->compare_at_price_amount);
        $this->assertNull($variant->compare_at_price_currency);
    }

    public function test_compare_at_price_requires_currency_when_amount_present(): void
    {
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Compare_at_price_amount requires a matching currency.');
        ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-COMPARE-NO-CURRENCY',
            'compare_at_price_amount' => 30000000,
            'compare_at_price_currency' => null,
        ]);
    }

    public function test_compare_at_price_amount_without_currency_rejected_by_database(): void
    {
        $product = $this->createProduct();

        $this->expectException(QueryException::class);
        DB::table('product_variants')->insert([
            'product_id' => $product->id,
            'sku' => 'SKU-DB-COMPARE-AMOUNT-ONLY',
            'variant_name' => 'DB Compare Amount Only',
            'price_amount' => 10000,
            'price_currency' => 'TZS',
            'compare_at_price_amount' => 20000,
            'compare_at_price_currency' => null,
            'is_default' => false,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_compare_at_price_currency_without_amount_rejected_by_database(): void
    {
        $product = $this->createProduct();

        $this->expectException(QueryException::class);
        DB::table('product_variants')->insert([
            'product_id' => $product->id,
            'sku' => 'SKU-DB-COMPARE-CURRENCY-ONLY',
            'variant_name' => 'DB Compare Currency Only',
            'price_amount' => 10000,
            'price_currency' => 'TZS',
            'compare_at_price_amount' => null,
            'compare_at_price_currency' => 'TZS',
            'is_default' => false,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cost_price_is_optional_and_requires_currency_when_present(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-COST',
            'cost_price_amount' => 20000000,
            'cost_price_currency' => 'TZS',
        ]);

        $this->assertSame(20000000, $variant->cost_price_amount);
        $this->assertSame('TZS', $variant->cost_price_currency);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cost_price_amount requires a matching currency.');
        ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-COST-NO-CURRENCY',
            'cost_price_amount' => 15000000,
            'cost_price_currency' => null,
        ]);
    }

    public function test_dimensions_and_weight_support_precision(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-DIMS',
            'width_cm' => 85.5,
            'height_cm' => 90.25,
            'depth_cm' => 80.0,
            'weight_kg' => 18.5,
        ])->fresh();

        $this->assertSame(85.5, (float) $variant->width_cm);
        $this->assertSame(90.25, (float) $variant->height_cm);
        $this->assertSame(80.0, (float) $variant->depth_cm);
        $this->assertSame(18.5, (float) $variant->weight_kg);
    }

    public function test_zero_width_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Variant width must be greater than zero.');
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-ZERO-WIDTH', 'width_cm' => 0]);
    }

    public function test_negative_height_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Variant height must be greater than zero.');
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-NEG-HEIGHT', 'height_cm' => -5]);
    }

    public function test_zero_depth_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Variant depth must be greater than zero.');
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-ZERO-DEPTH', 'depth_cm' => 0]);
    }

    public function test_negative_weight_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Variant weight must be greater than zero.');
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-NEG-WEIGHT', 'weight_kg' => -5]);
    }

    public function test_attributes_are_nullable(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-NO-ATTRS',
            'attributes' => null,
        ]);

        $this->assertNull($variant->attributes);
    }

    public function test_attributes_round_trip_as_structured_json_object(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'SKU-ATTRS',
            'attributes' => [
                'color' => 'Forest Green',
                'fabric' => 'Velvet',
                'leg_finish' => 'Natural Oak',
                'size' => '3-Seater',
            ],
        ]);

        $this->assertSame('Forest Green', $variant->attributes['color']);
        $this->assertSame('Velvet', $variant->attributes['fabric']);
        $this->assertSame('Natural Oak', $variant->attributes['leg_finish']);
        $this->assertSame('3-Seater', $variant->attributes['size']);
    }

    public function test_default_variant_is_singular_per_product(): void
    {
        $product = $this->createProduct();
        ProductVariant::factory()->for($product)->asDefault()->create(['sku' => 'SKU-DEFAULT-1']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A product can only have one default variant.');
        ProductVariant::factory()->for($product)->asDefault()->create(['sku' => 'SKU-DEFAULT-2']);
    }

    public function test_database_prevents_two_defaults_via_direct_insert_bypassing_model_validation(): void
    {
        $product = $this->createProduct();

        DB::table('product_variants')->insert([
            'product_id' => $product->id,
            'sku' => 'SKU-DB-DEFAULT-1',
            'variant_name' => 'DB Variant 1',
            'price_amount' => 10000,
            'price_currency' => 'TZS',
            'is_default' => true,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('product_variants')->insert([
            'product_id' => $product->id,
            'sku' => 'SKU-DB-DEFAULT-2',
            'variant_name' => 'DB Variant 2',
            'price_amount' => 20000,
            'price_currency' => 'TZS',
            'is_default' => true,
            'is_active' => true,
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_transactional_default_switch_leaves_exactly_one_default(): void
    {
        $product = $this->createProduct();
        $first = ProductVariant::factory()->for($product)->asDefault()->create(['sku' => 'SKU-SWITCH-1']);
        $second = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-SWITCH-2']);

        app(ProductVariantService::class)->setAsDefault($second);

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, ProductVariant::where('product_id', $product->id)->where('is_default', true)->count());
        $this->assertSame($second->id, $product->fresh()->defaultVariant->id);
    }

    public function test_different_products_can_each_have_a_default_variant(): void
    {
        $firstProduct = $this->createProduct();
        $secondProduct = $this->createProduct();
        ProductVariant::factory()->for($firstProduct)->asDefault()->create(['sku' => 'SKU-DEF-1']);
        ProductVariant::factory()->for($secondProduct)->asDefault()->create(['sku' => 'SKU-DEF-2']);

        $this->assertSame('SKU-DEF-1', $firstProduct->fresh()->defaultVariant->sku);
        $this->assertSame('SKU-DEF-2', $secondProduct->fresh()->defaultVariant->sku);
    }

    public function test_default_variant_returns_the_default_record_only(): void
    {
        $product = $this->createProduct();
        $default = ProductVariant::factory()->for($product)->asDefault()->create(['sku' => 'SKU-DEFAULT']);
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-OTHER']);

        $defaultVariant = $product->fresh()->defaultVariant;

        $this->assertNotNull($defaultVariant);
        $this->assertTrue($defaultVariant->is($default));
        $this->assertSame('SKU-DEFAULT', $defaultVariant->sku);
    }

    public function test_product_without_default_variant_returns_none(): void
    {
        $product = $this->createProduct();
        ProductVariant::factory()->for($product)->create(['sku' => 'SKU-NO-DEFAULT']);

        $this->assertNull($product->fresh()->defaultVariant);
    }

    public function test_is_default_and_is_active_are_boolean_casts(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->inactive()->asDefault()->create(['sku' => 'SKU-BOOLS']);

        $this->assertIsBool($variant->is_default);
        $this->assertTrue($variant->is_default);
        $this->assertIsBool($variant->is_active);
        $this->assertFalse($variant->is_active);
    }

    public function test_inactive_variant_is_not_deleted(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->inactive()->create(['sku' => 'SKU-INACTIVE']);

        $this->assertFalse($variant->is_active);
        $this->assertSame(1, ProductVariant::count());
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_variant_does_not_store_inventory_or_media_fields(): void
    {
        $this->assertFalse(Schema::hasColumn('product_variants', 'quantity'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'reserved_quantity'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'available_quantity'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'warehouse_location'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'stock_status'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'image_url'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'glb_url'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'price'));
    }

    public function test_money_is_not_stored_as_decimal_or_float(): void
    {
        $schema = Schema::getColumnListing('product_variants');

        $this->assertTrue(in_array('price_amount', $schema, true));
        $this->assertFalse(in_array('price', $schema, true));
        $this->assertFalse(in_array('cost_price', $schema, true));
        $this->assertFalse(in_array('compare_at_price', $schema, true));
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
