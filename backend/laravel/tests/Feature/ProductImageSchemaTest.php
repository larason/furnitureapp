<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImageSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_images_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('product_images'));
        $this->assertTrue(Schema::hasColumns('product_images', [
            'id',
            'product_id',
            'product_variant_id',
            'file_path',
            'alt_text',
            'sort_order',
            'is_primary',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_image_belongs_to_a_product(): void
    {
        $product = $this->createProduct();
        $image = ProductImage::factory()->for($product)->create([
            'file_path' => 'products/1/gallery/front.webp',
        ]);

        $this->assertSame($product->id, $image->product_id);
        $this->assertTrue($image->product->is($product));
    }

    public function test_product_returns_its_images(): void
    {
        $product = $this->createProduct();
        $first = ProductImage::factory()->for($product)->create(['sort_order' => 2]);
        $second = ProductImage::factory()->for($product)->create(['sort_order' => 1]);

        $images = $product->fresh()->images;

        $this->assertCount(2, $images);
        $this->assertTrue($images->contains($first));
        $this->assertTrue($images->contains($second));
    }

    public function test_images_are_ordered_by_sort_order_and_id_deterministically(): void
    {
        $product = $this->createProduct();
        $imgA = ProductImage::factory()->for($product)->create(['sort_order' => 10]);
        $imgB = ProductImage::factory()->for($product)->create(['sort_order' => 1]);
        $imgC = ProductImage::factory()->for($product)->create(['sort_order' => 5]);
        $imgD = ProductImage::factory()->for($product)->create(['sort_order' => 5]);

        $orderedIds = $product->fresh()->images->pluck('id')->all();

        $this->assertSame([$imgB->id, $imgC->id, $imgD->id, $imgA->id], $orderedIds);
    }

    public function test_invalid_product_id_is_rejected_by_foreign_key(): void
    {
        $this->expectException(QueryException::class);
        ProductImage::query()->create([
            'product_id' => 999999,
            'file_path' => 'products/999999/gallery/orphan.webp',
        ]);
    }

    public function test_deleting_product_cascades_to_images(): void
    {
        $product = $this->createProduct();
        ProductImage::factory()->for($product)->create();
        ProductImage::factory()->for($product)->create();

        $this->assertSame(2, ProductImage::count());

        $product->forceDelete();

        $this->assertSame(0, ProductImage::count());
        $this->assertSame(0, DB::table('product_images')->count());
    }

    public function test_image_can_optionally_belong_to_a_variant(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        $image = ProductImage::factory()->forVariant($variant)->create();

        $this->assertSame($variant->id, $image->product_variant_id);
        $this->assertTrue($image->productVariant->is($variant));
        $this->assertTrue($variant->images->contains($image));
    }

    public function test_variant_returns_only_its_associated_images_ordered(): void
    {
        $product = $this->createProduct();
        $variant1 = ProductVariant::factory()->for($product)->create();
        $variant2 = ProductVariant::factory()->for($product)->create();

        $imgV1A = ProductImage::factory()->forVariant($variant1)->create(['sort_order' => 5]);
        $imgV1B = ProductImage::factory()->forVariant($variant1)->create(['sort_order' => 1]);
        $imgV2 = ProductImage::factory()->forVariant($variant2)->create();
        $imgGeneral = ProductImage::factory()->for($product)->create();

        $v1Images = $variant1->fresh()->images;

        $this->assertCount(2, $v1Images);
        $this->assertSame([$imgV1B->id, $imgV1A->id], $v1Images->pluck('id')->all());
        $this->assertFalse($v1Images->contains($imgV2));
        $this->assertFalse($v1Images->contains($imgGeneral));
    }

    public function test_deleting_variant_nulls_product_variant_id_on_image(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        $image = ProductImage::factory()->forVariant($variant)->create();

        $this->assertSame($variant->id, $image->product_variant_id);

        $variant->delete();

        $freshImage = $image->fresh();
        $this->assertNotNull($freshImage);
        $this->assertNull($freshImage->product_variant_id);
        $this->assertSame($product->id, $freshImage->product_id);
    }

    public function test_variant_from_different_product_is_rejected_by_application(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();
        $variantB = ProductVariant::factory()->for($productB)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product image variant must belong to the same product.');

        ProductImage::query()->create([
            'product_id' => $productA->id,
            'product_variant_id' => $variantB->id,
            'file_path' => 'products/cross/image.webp',
        ]);
    }

    public function test_variant_from_same_product_is_accepted(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        $image = new ProductImage([
            'product_variant_id' => $variant->id,
            'file_path' => 'products/valid/image.webp',
        ]);
        $image->product_id = $product->id;
        $image->save();

        $this->assertSame($variant->id, $image->fresh()->product_variant_id);
    }

    public function test_single_primary_image_per_product_enforced_by_application(): void
    {
        $product = $this->createProduct();
        ProductImage::factory()->for($product)->asPrimary()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A product can only have one primary image.');

        ProductImage::factory()->for($product)->asPrimary()->create();
    }

    public function test_single_primary_image_per_product_enforced_by_database_constraint(): void
    {
        $product = $this->createProduct();

        DB::table('product_images')->insert([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'file_path' => 'products/test/primary-1.webp',
            'alt_text' => 'First primary',
            'sort_order' => 0,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('product_images')->insert([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'file_path' => 'products/test/primary-2.webp',
            'alt_text' => 'Second primary',
            'sort_order' => 1,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_multiple_non_primary_images_are_allowed_per_product(): void
    {
        $product = $this->createProduct();
        $img1 = ProductImage::factory()->for($product)->create(['is_primary' => false]);
        $img2 = ProductImage::factory()->for($product)->create(['is_primary' => false]);
        $img3 = ProductImage::factory()->for($product)->create(['is_primary' => false]);

        $this->assertSame(3, ProductImage::where('product_id', $product->id)->count());
        $this->assertFalse($img1->is_primary);
        $this->assertFalse($img2->is_primary);
        $this->assertFalse($img3->is_primary);
    }

    public function test_different_products_can_each_have_a_primary_image(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();

        $primaryA = ProductImage::factory()->for($productA)->asPrimary()->create();
        $primaryB = ProductImage::factory()->for($productB)->asPrimary()->create();

        $this->assertTrue($productA->fresh()->primaryImage->is($primaryA));
        $this->assertTrue($productB->fresh()->primaryImage->is($primaryB));
    }

    public function test_product_primary_image_relationship_returns_only_primary(): void
    {
        $product = $this->createProduct();
        ProductImage::factory()->for($product)->create(['is_primary' => false]);
        $primary = ProductImage::factory()->for($product)->asPrimary()->create();
        ProductImage::factory()->for($product)->create(['is_primary' => false]);

        $this->assertTrue($product->fresh()->primaryImage->is($primary));
    }

    public function test_product_without_primary_image_returns_null_for_primary_image(): void
    {
        $product = $this->createProduct();
        ProductImage::factory()->for($product)->create(['is_primary' => false]);

        $this->assertNull($product->fresh()->primaryImage);
    }

    public function test_alt_text_can_be_null_or_valid_string(): void
    {
        $product = $this->createProduct();
        $imgWithNull = ProductImage::factory()->for($product)->create(['alt_text' => null]);
        $imgWithText = ProductImage::factory()->for($product)->create([
            'alt_text' => 'Forest green lounge chair with natural oak legs',
        ]);

        $this->assertNull($imgWithNull->alt_text);
        $this->assertSame('Forest green lounge chair with natural oak legs', $imgWithText->alt_text);
    }

    public function test_defaults_and_casts_for_product_image(): void
    {
        $product = $this->createProduct();
        $image = new ProductImage([
            'file_path' => 'products/casts/test.webp',
        ]);
        $image->product_id = $product->id;
        $image->save();
        $image = $image->fresh();

        $this->assertSame(0, $image->sort_order);
        $this->assertFalse($image->is_primary);
        $this->assertIsInt($image->sort_order);
        $this->assertIsBool($image->is_primary);
    }

    public function test_product_image_does_not_contain_speculative_media_or_technical_columns(): void
    {
        $columns = Schema::getColumnListing('product_images');

        $this->assertNotContains('glb_path', $columns);
        $this->assertNotContains('usdz_path', $columns);
        $this->assertNotContains('gltf_path', $columns);
        $this->assertNotContains('model_url', $columns);
        $this->assertNotContains('ar_url', $columns);
        $this->assertNotContains('video_url', $columns);
        $this->assertNotContains('width_px', $columns);
        $this->assertNotContains('height_px', $columns);
        $this->assertNotContains('filesize', $columns);
        $this->assertNotContains('mime_type', $columns);
        $this->assertNotContains('checksum', $columns);
        $this->assertNotContains('blurhash', $columns);
        $this->assertNotContains('dominant_color', $columns);
        $this->assertNotContains('url', $columns);
        $this->assertNotContains('type', $columns);
        $this->assertNotContains('status', $columns);
        $this->assertNotContains('is_published', $columns);
        $this->assertNotContains('is_visible', $columns);
    }

    public function test_file_path_stores_internal_reference_not_public_url(): void
    {
        $product = $this->createProduct();
        $image = ProductImage::factory()->for($product)->create([
            'file_path' => 'products/123/gallery/front.jpg',
        ]);

        $this->assertSame('products/123/gallery/front.jpg', $image->file_path);
        $this->assertFalse(str_starts_with($image->file_path, 'http://'));
        $this->assertFalse(str_starts_with($image->file_path, 'https://'));
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
