<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_collection_returns_allow_listed_paginated_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Oak Table', 'slug' => 'oak-table']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 125000000]);
        ProductStock::factory()->forVariant($variant)->create(['quantity' => 5, 'reserved_quantity' => 1]);
        ProductImage::factory()->for($product)->asPrimary()->create(['file_path' => 'products/oak.webp']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public, s-maxage=600')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Oak Table')
            ->assertJsonPath('data.0.price.amount', 125000000)
            ->assertJsonPath('data.0.price.currency', 'TZS')
            ->assertJsonPath('data.0.availability', 'available')
            ->assertJsonMissingPath('data.0.product_type')
            ->assertJsonMissingPath('data.0.is_active')
            ->assertJsonMissingPath('data.0.primary_image.file_path')
            ->assertJsonMissingPath('data.0.stock_indicator')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'price', 'category', 'primary_image', 'availability']], 'meta' => ['pagination']]);
    }

    public function test_detail_resolves_by_slug_and_opaque_id_with_ordered_active_variants(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'slug' => 'oak-table', 'description' => 'A solid oak table.']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'variant_name' => 'Large', 'display_order' => 2, 'price_amount' => 200]);
        ProductVariant::factory()->inactive()->create(['product_id' => $product->id, 'variant_name' => 'Hidden', 'display_order' => 1, 'price_amount' => 100]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2, 'file_path' => 'products/two.webp']);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1, 'file_path' => 'products/one.webp']);

        foreach (['oak-table', 'prod_'.base_convert((string) $product->id, 10, 36)] as $identifier) {
            $this->getJson('/api/v1/products/'.$identifier)
                ->assertOk()
                ->assertJsonPath('data.description', 'A solid oak table.')
                ->assertJsonPath('data.variants.0.name', 'Large')
                ->assertJsonCount(1, 'data.variants')
                ->assertJsonPath('data.images.0.sort_order', 1)
                ->assertJsonMissingPath('data.stock_indicator')
                ->assertJsonMissingPath('data.variants.0.stock_indicator')
                ->assertJsonMissingPath('data.variants.0.product_id')
                ->assertJsonMissingPath('data.images.0.file_path')
                ->assertJsonMissingPath('data.cost_price_amount');
        }
    }

    public function test_inactive_and_deleted_products_are_masked(): void
    {
        $inactive = Product::factory()->inactive()->create(['slug' => 'inactive-table']);
        $deleted = Product::factory()->create(['slug' => 'deleted-table']);
        $deleted->delete();

        $this->getJson('/api/v1/products')->assertJsonMissing(['slug' => 'inactive-table'])->assertJsonMissing(['slug' => 'deleted-table']);
        $this->getJson('/api/v1/products/'.$inactive->slug)->assertNotFound()->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        $this->getJson('/api/v1/products/'.$deleted->slug)->assertNotFound()->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_collection_validates_price_range_and_sort_allow_list(): void
    {
        $this->getJson('/api/v1/products?min_price=20&max_price=10')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors', 'meta' => ['request_id']]);

        $this->getJson('/api/v1/products?sort=cost_price')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors', 'meta' => ['request_id']]);
    }

    public function test_price_filters_and_sorting_use_each_products_minimum_active_variant_price(): void
    {
        $category = Category::factory()->create();
        $expensive = Product::factory()->create(['category_id' => $category->id, 'name' => 'Expensive', 'slug' => 'expensive']);
        $cheap = Product::factory()->create(['category_id' => $category->id, 'name' => 'Cheap', 'slug' => 'cheap']);
        ProductVariant::factory()->create(['product_id' => $expensive->id, 'price_amount' => 50]);
        ProductVariant::factory()->create(['product_id' => $expensive->id, 'price_amount' => 300]);
        ProductVariant::factory()->create(['product_id' => $cheap->id, 'price_amount' => 100]);

        $this->getJson('/api/v1/products?min_price=100&sort=price')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.slug', 'cheap')
            ->assertJsonPath('data.0.price.amount', 100);
    }
}
