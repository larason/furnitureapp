<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\AssemblyRequired;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use ValueError;

class ProductSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_exist_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasColumns('products', ['price_amount', 'price_currency']));
    }

    public function test_product_belongs_to_a_category(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $product = Product::factory()->for($category)->create(['slug' => 'nordic-sofa']);

        $this->assertSame($category->id, $product->category_id);
        $this->assertTrue($product->category->is($category));
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        Product::factory()->for($category)->create(['slug' => 'shared-slug']);

        $this->expectException(QueryException::class);
        Product::factory()->for($category)->create(['slug' => 'shared-slug']);
    }

    public function test_invalid_category_id_is_rejected_by_foreign_key(): void
    {
        $this->expectException(QueryException::class);
        Product::query()->create([
            'category_id' => 999999,
            'name' => 'Orphan Product',
            'slug' => 'orphan-product',
            'price_amount' => 100,
            'price_currency' => 'TZS',
        ]);
    }

    public function test_deleting_a_category_with_products_is_restricted(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $product = Product::factory()->for($category)->create(['slug' => 'nordic-sofa']);

        try {
            $category->delete();
            $this->fail('Deleting a category that has products must be rejected.');
        } catch (QueryException) {
            // restrictive FK — expected
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_empty_product_name_is_rejected(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A product name is required.');
        Product::factory()->for($category)->create(['name' => '']);
    }

    public function test_whitespace_only_product_name_is_rejected(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A product name is required.');
        Product::factory()->for($category)->create(['name' => '   ']);
    }

    public function test_product_slug_must_use_kebab_case(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A product slug must use kebab-case.');
        Product::factory()->for($category)->create(['slug' => 'Nordic Sofa']);
    }

    public function test_active_and_featured_are_boolean_casts(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $product = Product::factory()->for($category)
            ->featured()
            ->inactive()
            ->create(['slug' => 'nordic-sofa']);

        $this->assertIsBool($product->is_active);
        $this->assertFalse($product->is_active);
        $this->assertIsBool($product->is_featured);
        $this->assertTrue($product->is_featured);
    }

    public function test_assembly_required_uses_controlled_values(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $product = Product::factory()->for($category)
            ->create(['slug' => 'nordic-sofa', 'assembly_required' => AssemblyRequired::FULL]);

        $this->assertInstanceOf(AssemblyRequired::class, $product->assembly_required);
        $this->assertSame(AssemblyRequired::FULL, $product->assembly_required);

        $defaulted = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Defaulted Product',
            'slug' => 'defaulted-product',
            'price_amount' => 100,
            'price_currency' => 'TZS',
        ])->fresh();
        $this->assertSame(AssemblyRequired::NONE, $defaulted->assembly_required);
    }

    public function test_unknown_assembly_required_value_is_rejected_by_model_cast(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);

        $this->expectException(ValueError::class);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Invalid Assembly Product',
            'slug' => 'invalid-assembly-product',
            'price_amount' => 100,
            'price_currency' => 'TZS',
            'assembly_required' => 'banana',
        ]);
    }

    public function test_unknown_assembly_required_value_is_rejected_by_database(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);

        $this->expectException(QueryException::class);
        DB::table('products')->insert([
            'category_id' => $category->id,
            'name' => 'Invalid Raw Product',
            'slug' => 'invalid-raw-product',
            'assembly_required' => 'banana',
        ]);
        $this->assertDatabaseMissing('products', ['slug' => 'invalid-raw-product']);
    }

    public function test_sku_prefix_is_optional_and_unique_per_non_null_value(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $first = Product::factory()->for($category)->create([
            'slug' => 'sofa-a',
            'sku_prefix' => 'SOF-1',
        ]);
        $second = Product::factory()->for($category)->create([
            'slug' => 'sofa-b',
            'sku_prefix' => 'SOF-2',
        ]);

        $this->assertSame('SOF-1', $first->sku_prefix);
        $this->assertSame('SOF-2', $second->sku_prefix);

        $this->expectException(QueryException::class);
        Product::factory()->for($category)->create([
            'slug' => 'sofa-c',
            'sku_prefix' => 'SOF-1',
        ]);
    }

    public function test_deleting_product_soft_deletes_and_hides_it(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $product = Product::factory()->for($category)->create(['slug' => 'nordic-sofa']);
        $variant = ProductVariant::factory()->for($product)->create();

        $product->delete();

        $this->assertNull(Product::where('slug', 'nordic-sofa')->first());
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertNotNull(Product::withTrashed()->find($product->id)->deleted_at);
        $this->assertNotNull(ProductVariant::find($variant->id));
    }

    public function test_force_deleting_product_hard_deletes_and_cascades_variants(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $product = Product::factory()->for($category)->create(['slug' => 'nordic-sofa']);
        $variant = ProductVariant::factory()->for($product)->create();

        $product->forceDelete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }

    public function test_product_does_not_require_variant_inventory_or_media_at_schema_level(): void
    {
        $category = $this->createCategory(['slug' => 'sofas']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Bare Product',
            'slug' => 'bare-product',
            'price_amount' => 100,
            'price_currency' => 'TZS',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku_prefix' => null,
            'brand' => null,
            'room_type' => null,
            'primary_material' => null,
        ]);
        $this->assertTrue(! Schema::hasColumn('products', 'price'));
        $this->assertTrue(! Schema::hasColumn('products', 'variant_sku'));
        $this->assertTrue(! Schema::hasColumn('products', 'stock'));
        $this->assertTrue(! Schema::hasColumn('products', 'image_url'));
    }

    public function test_product_base_price_rejects_missing_or_invalid_values_at_database_level(): void
    {
        $category = $this->createCategory(['slug' => 'priced-products']);
        $payload = [
            'category_id' => $category->id,
            'name' => 'Invalid Product Price',
            'slug' => 'invalid-product-price',
            'price_amount' => 100,
            'price_currency' => 'TZS',
        ];

        foreach ([
            ['price_amount' => null],
            ['price_amount' => -1],
            ['price_currency' => 'USD'],
        ] as $invalidValues) {
            try {
                DB::table('products')->insert([...$payload, ...$invalidValues]);
                $this->fail('Invalid Product base price must be rejected by the database.');
            } catch (QueryException) {
                $this->assertDatabaseMissing('products', ['slug' => $payload['slug']]);
            }
        }
    }

    public function test_publication_controls_are_not_mass_assignable(): void
    {
        $product = Product::factory()->create();
        $originalType = $product->product_type;
        $originalPublished = $product->is_published;

        $product->fill([
            'product_type' => 'MADE_TO_ORDER',
            'is_published' => false,
        ]);

        $this->assertSame($originalType, $product->product_type);
        $this->assertSame($originalPublished, $product->is_published);
    }

    private function createCategory(array $attributes = []): Category
    {
        $category = new Category([
            'name' => $attributes['name'] ?? 'Category',
            'slug' => $attributes['slug'] ?? Str::random(10),
            'space_type' => $attributes['space_type'] ?? 'home',
            'display_order' => $attributes['display_order'] ?? 0,
        ]);
        $category->save();

        return $category->fresh();
    }
}
