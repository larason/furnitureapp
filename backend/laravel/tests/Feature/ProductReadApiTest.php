<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_identifier_round_trips_maximum_supported_integer_key(): void
    {
        $variant = new ProductVariant;
        $variant->setAttribute('id', PHP_INT_MAX);

        $encoded = VariantIdentifier::encode($variant);

        $this->assertSame(PHP_INT_MAX, VariantIdentifier::decode($encoded));
    }

    public function test_public_collection_returns_allow_listed_paginated_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Oak Table', 'slug' => 'oak-table', 'price_amount' => 125000000]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 125000000]);
        ProductStock::factory()->forVariant($variant)->create(['quantity' => 5, 'reserved_quantity' => 1]);
        ProductImage::factory()->for($product)->asPrimary()->create(['file_path' => 'products/oak.webp']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'public')
            ->assertHeaderContains('Cache-Control', 'max-age=300')
            ->assertHeaderContains('Cache-Control', 's-maxage=600')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Oak Table')
            ->assertJsonPath('data.0.price.amount', 125000000)
            ->assertJsonPath('data.0.price.currency', 'TZS')
            ->assertJsonPath('data.0.availability', 'available')
            ->assertJsonPath('data.0.product_type', 'IN_STOCK')
            ->assertJsonMissingPath('data.0.is_active')
            ->assertJsonMissingPath('data.0.primary_image.file_path')
            ->assertJsonPath('data.0.stock_indicator', 'LOW_STOCK')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'product_type', 'price', 'category', 'primary_image', 'availability', 'stock_indicator']], 'meta' => ['pagination']]);
    }

    public function test_request_only_mode_hides_legacy_in_stock_products_from_public_reads(): void
    {
        config(['commerce.request_only' => true]);
        $category = Category::factory()->create(['is_active' => true]);
        $inStock = Product::factory()->create(['category_id' => $category->id, 'slug' => 'legacy-in-stock', 'product_type' => 'IN_STOCK']);
        $madeToOrder = Product::factory()->create(['category_id' => $category->id, 'slug' => 'made-to-order', 'product_type' => 'MADE_TO_ORDER']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($madeToOrder));
        $this->getJson('/api/v1/products/'.$inStock->slug)->assertNotFound();
    }

    public function test_detail_resolves_by_slug_and_opaque_id_with_ordered_active_variants(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'slug' => 'oak-table', 'description' => 'A solid oak table.']);
        $activeVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'variant_name' => 'Large', 'display_order' => 2, 'price_amount' => 200]);
        ProductStock::factory()->forVariant($activeVariant)->create(['quantity' => 5, 'reserved_quantity' => 1]);
        ProductVariant::factory()->inactive()->create(['product_id' => $product->id, 'variant_name' => 'Hidden', 'display_order' => 1, 'price_amount' => 100]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2, 'file_path' => 'products/two.webp']);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1, 'file_path' => 'products/one.webp']);

        foreach (['oak-table', 'prod_'.base_convert((string) $product->id, 10, 36)] as $identifier) {
            $this->getJson('/api/v1/products/'.$identifier)
                ->assertOk()
                ->assertJsonPath('data.description', 'A solid oak table.')
                ->assertJsonPath('data.variants.0.name', 'Large')
                ->assertJsonPath('data.variants.0.availability', 'available')
                ->assertJsonCount(1, 'data.variants')
                ->assertJsonPath('data.images.0.sort_order', 1)
                ->assertJsonPath('data.category.id', 'cat_'.base_convert((string) $category->id, 10, 36))
                ->assertJsonPath('data.product_type', 'IN_STOCK')
                ->assertJsonPath('data.stock_indicator', 'LOW_STOCK')
                ->assertJsonPath('data.variants.0.stock_indicator', 'LOW_STOCK')
                ->assertJsonMissingPath('data.variants.0.product_id')
                ->assertJsonMissingPath('data.images.0.file_path')
                ->assertJsonMissingPath('data.cost_price_amount');
        }
    }

    public function test_detail_matches_collection_summary_and_has_no_image_or_variant_leaks(): void
    {
        $category = Category::factory()->create(['description' => 'Dining furniture']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'no-image-table',
            'description' => 'A compact dining table.',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 875000,
            'price_currency' => 'TZS',
            'cost_price_amount' => 300000,
            'cost_price_currency' => 'TZS',
        ]);
        ProductStock::factory()->forVariant($variant)->create(['quantity' => 2, 'reserved_quantity' => 2]);

        $collection = $this->getJson('/api/v1/products')->assertOk();
        $detail = $this->getJson('/api/v1/products/'.$product->slug)->assertOk();

        $detail->assertJsonPath('data.name', $collection->json('data.0.name'))
            ->assertJsonPath('data.price.amount', $collection->json('data.0.price.amount'))
            ->assertJsonPath('data.availability', 'unavailable')
            ->assertJsonPath('data.category.description', 'Dining furniture')
            ->assertJsonCount(0, 'data.images')
            ->assertJsonPath('data.variants.0.availability', 'unavailable')
            ->assertJsonMissingPath('data.variants.0.cost_price_amount')
            ->assertJsonMissingPath('data.variants.0.quantity')
            ->assertJsonMissingPath('data.variants.0.reserved_quantity');
    }

    public function test_detail_does_not_include_related_data_from_another_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'slug' => 'first-table']);
        $other = Product::factory()->create(['category_id' => $category->id, 'slug' => 'second-table']);
        $variant = ProductVariant::factory()->create(['product_id' => $other->id, 'variant_name' => 'Other size']);
        ProductStock::factory()->forVariant($variant)->create(['quantity' => 10, 'reserved_quantity' => 0]);
        ProductImage::factory()->create(['product_id' => $other->id, 'file_path' => 'products/other.webp']);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonCount(0, 'data.images')
            ->assertJsonCount(0, 'data.variants')
            ->assertJsonMissing(['name' => 'Other size']);
    }

    public function test_public_variant_collection_is_scoped_ordered_and_allow_listed(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'slug' => 'variant-table']);
        $first = ProductVariant::factory()->create(['product_id' => $product->id, 'display_order' => 2, 'variant_name' => 'Large', 'price_amount' => 200]);
        $second = ProductVariant::factory()->create(['product_id' => $product->id, 'display_order' => 1, 'variant_name' => 'Small', 'price_amount' => 100]);
        ProductVariant::factory()->inactive()->create(['product_id' => $product->id, 'variant_name' => 'Hidden']);
        ProductStock::factory()->forVariant($first)->create(['quantity' => 2, 'reserved_quantity' => 1]);

        $response = $this->getJson('/api/v1/products/'.$product->slug.'/variants')->assertOk();

        $response->assertJsonPath('data.0.id', VariantIdentifier::encode($second))
            ->assertJsonPath('data.1.id', VariantIdentifier::encode($first))
            ->assertJsonPath('data.0.product_id', 'prod_'.base_convert((string) $product->id, 10, 36))
            ->assertJsonPath('data.0.price.amount', 100)
            ->assertJsonPath('data.0.availability', 'unavailable')
            ->assertJsonPath('data.1.availability', 'available')
            ->assertJsonPath('data.0.stock_indicator', 'IN_STOCK')
            ->assertJsonMissingPath('data.0.cost_price_amount')
            ->assertJsonMissingPath('data.0.is_active')
            ->assertJsonMissingPath('data.0.display_order')
            ->assertJsonStructure(['data' => [['id', 'product_id', 'sku', 'name', 'price', 'availability', 'stock_indicator', 'created_at', 'updated_at']]]);
    }

    public function test_variant_detail_requires_public_parent_and_strict_parent_ownership(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'slug' => 'parent-table']);
        $other = Product::factory()->create(['category_id' => $category->id, 'slug' => 'other-table']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'variant_name' => 'Walnut']);
        $inactive = ProductVariant::factory()->inactive()->create(['product_id' => $product->id]);

        $this->getJson('/api/v1/products/'.$product->slug.'/variants/'.VariantIdentifier::encode($variant))
            ->assertOk()
            ->assertJsonPath('data.id', VariantIdentifier::encode($variant))
            ->assertJsonPath('data.stock_indicator', 'IN_STOCK');
        $this->getJson('/api/v1/products/'.$other->slug.'/variants/'.VariantIdentifier::encode($variant))
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        $this->getJson('/api/v1/products/'.$product->slug.'/variants/'.VariantIdentifier::encode($inactive))->assertNotFound();
        $product->update(['is_active' => false]);
        $this->getJson('/api/v1/products/'.$product->slug.'/variants')->assertNotFound();
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

    public function test_price_filters_and_sorting_use_each_products_base_price(): void
    {
        $category = Category::factory()->create();
        $expensive = Product::factory()->create(['category_id' => $category->id, 'name' => 'Expensive', 'slug' => 'expensive', 'price_amount' => 50]);
        $cheap = Product::factory()->create(['category_id' => $category->id, 'name' => 'Cheap', 'slug' => 'cheap', 'price_amount' => 100]);
        ProductVariant::factory()->create(['product_id' => $expensive->id, 'price_amount' => 50]);
        ProductVariant::factory()->create(['product_id' => $expensive->id, 'price_amount' => 300]);
        ProductVariant::factory()->create(['product_id' => $cheap->id, 'price_amount' => 100]);

        $this->getJson('/api/v1/products?min_price=100&sort=price')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.slug', 'cheap')
            ->assertJsonPath('data.0.price.amount', 100);
    }

    public function test_search_matches_name_description_sku_prefix_and_controlled_variant_attributes(): void
    {
        $category = Category::factory()->create();
        $nameProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Oak Dining Table', 'slug' => 'oak-dining-table']);
        $descriptionProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Dining Set', 'slug' => 'dining-set', 'description' => 'Solid walnut finish']);
        $skuProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Living Room Set', 'slug' => 'living-room-set']);
        $attributeProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Accent Chair', 'slug' => 'accent-chair']);

        ProductVariant::factory()->create(['product_id' => $nameProduct->id]);
        ProductVariant::factory()->create(['product_id' => $descriptionProduct->id]);
        ProductVariant::factory()->create(['product_id' => $skuProduct->id, 'sku' => 'SOFA-RED-3S']);
        ProductVariant::factory()->create([
            'product_id' => $attributeProduct->id,
            'attributes' => ['color' => 'Forest Green', 'material' => 'Velvet'],
        ]);

        $this->getJson('/api/v1/products?search=oak')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->getJson('/api/v1/products?search=walnut')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->getJson('/api/v1/products?search=SOFA-RED')->assertOk()->assertJsonPath('data.0.slug', 'living-room-set');
        $this->getJson('/api/v1/products?search=green')->assertOk()->assertJsonPath('data.0.slug', 'accent-chair');
    }

    public function test_search_ignores_matching_inactive_variants(): void
    {
        $category = Category::factory()->create();
        $inactiveOnly = Product::factory()->create(['category_id' => $category->id, 'name' => 'Plain Chair', 'slug' => 'plain-chair']);
        ProductVariant::factory()->inactive()->create(['product_id' => $inactiveOnly->id, 'sku' => 'HIDDEN-OAK']);

        $this->getJson('/api/v1/products?search=HIDDEN-OAK')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_sqlite_product_search_escapes_like_metacharacters(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Oak 100% Table', 'slug' => 'oak-percent-table']);
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Oak 100X Table', 'slug' => 'oak-100x-table']);

        $this->getJson('/api/v1/products?search='.urlencode('100%'))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.slug', 'oak-percent-table');
    }

    public function test_sqlite_sku_search_escapes_like_metacharacters(): void
    {
        $category = Category::factory()->create();
        $percentProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Percent SKU Product', 'slug' => 'percent-sku-product']);
        $underscoreProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Underscore SKU Product', 'slug' => 'underscore-sku-product']);
        $wildcardProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Wildcard SKU Product', 'slug' => 'wildcard-sku-product']);

        ProductVariant::factory()->create(['product_id' => $percentProduct->id, 'sku' => 'CODE%RED']);
        ProductVariant::factory()->create(['product_id' => $underscoreProduct->id, 'sku' => 'CODE_RED']);
        ProductVariant::factory()->create(['product_id' => $wildcardProduct->id, 'sku' => 'CODERED']);

        $this->getJson('/api/v1/products?search='.urlencode('CODE%'))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.slug', 'percent-sku-product');

        $this->getJson('/api/v1/products?search='.urlencode('CODE_'))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.slug', 'underscore-sku-product');
    }

    public function test_search_composes_with_category_and_price_filters_and_suppresses_duplicate_variants(): void
    {
        $category = Category::factory()->create(['slug' => 'dining-room']);
        $otherCategory = Category::factory()->create(['slug' => 'living-room']);
        $matching = Product::factory()->create(['category_id' => $category->id, 'name' => 'Oak Dining Table', 'slug' => 'matching-table', 'price_amount' => 100]);
        ProductVariant::factory()->create(['product_id' => $matching->id, 'price_amount' => 100, 'sku' => 'OAK-ONE']);
        ProductVariant::factory()->create(['product_id' => $matching->id, 'price_amount' => 200, 'sku' => 'OAK-TWO']);
        $wrongCategory = Product::factory()->create(['category_id' => $otherCategory->id, 'name' => 'Oak Lounge Table', 'slug' => 'wrong-category']);
        ProductVariant::factory()->create(['product_id' => $wrongCategory->id, 'price_amount' => 100]);
        $wrongPrice = Product::factory()->create(['category_id' => $category->id, 'name' => 'Oak Sideboard', 'slug' => 'wrong-price', 'price_amount' => 500]);
        ProductVariant::factory()->create(['product_id' => $wrongPrice->id, 'price_amount' => 500]);

        $this->getJson('/api/v1/products?search=oak&category=dining-room&min_price=100&max_price=200')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.slug', 'matching-table');
    }

    public function test_empty_search_matches_unfiltered_collection_and_hidden_products_do_not_match(): void
    {
        $category = Category::factory()->create();
        $visible = Product::factory()->create(['category_id' => $category->id, 'name' => 'Visible Oak', 'slug' => 'visible-oak']);
        ProductVariant::factory()->create(['product_id' => $visible->id]);
        $hidden = Product::factory()->inactive()->create(['category_id' => $category->id, 'name' => 'Hidden Oak', 'slug' => 'hidden-oak']);
        ProductVariant::factory()->create(['product_id' => $hidden->id]);

        $withoutSearch = $this->getJson('/api/v1/products')->assertOk();
        $withWhitespace = $this->getJson('/api/v1/products?search='.urlencode('  '))->assertOk();
        $withSearch = $this->getJson('/api/v1/products?search=oak')->assertOk();

        $this->assertSame($withoutSearch->json('meta.pagination.total'), $withWhitespace->json('meta.pagination.total'));
        $withSearch->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.slug', 'visible-oak');
    }

    public function test_product_type_filter_uses_authoritative_product_type(): void
    {
        Product::factory()->madeToOrder()->create();

        $this->getJson('/api/v1/products?product_type=IN_STOCK')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_made_to_order_products_are_available_without_stock_and_expose_request_type(): void
    {
        $product = Product::factory()->madeToOrder()->create(['name' => 'Custom Walnut Desk']);

        $this->getJson('/api/v1/products?product_type=MADE_TO_ORDER&availability=available')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($product))
            ->assertJsonPath('data.0.product_type', 'MADE_TO_ORDER')
            ->assertJsonPath('data.0.availability', 'available')
            ->assertJsonPath('data.0.stock_indicator', 'MADE_TO_ORDER');
    }

    public function test_unpublished_products_are_hidden_from_collection_and_detail(): void
    {
        $product = Product::factory()->draft()->create(['slug' => 'draft-table']);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonMissing(['slug' => 'draft-table']);
        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_stock_indicator_aggregates_available_quantity_across_locations(): void
    {
        $product = Product::factory()->create(['name' => 'Multi-location Table']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductStock::factory()->forVariant($variant)->create([
            'warehouse_location' => 'main',
            'quantity' => 4,
            'reserved_quantity' => 1,
        ]);
        ProductStock::factory()->forVariant($variant)->create([
            'warehouse_location' => 'secondary',
            'quantity' => 3,
            'reserved_quantity' => 0,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.stock_indicator', 'IN_STOCK');
    }

    public function test_default_pagination_uses_twenty_items_and_filtered_totals(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(21)->create(['category_id' => $category->id]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonPath('meta.pagination.total', 21)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.has_next', true)
            ->assertJsonPath('meta.pagination.has_previous', false);
    }

    public function test_custom_pages_and_empty_beyond_last_page_preserve_pagination_contract(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(5)->create(['category_id' => $category->id]);

        $this->getJson('/api/v1/products?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 5)
            ->assertJsonPath('meta.pagination.last_page', 3)
            ->assertJsonPath('meta.pagination.has_next', true)
            ->assertJsonPath('meta.pagination.has_previous', true);

        $this->getJson('/api/v1/products?per_page=2&page=9')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.current_page', 3)
            ->assertJsonPath('meta.pagination.last_page', 3)
            ->assertJsonPath('meta.pagination.has_next', false)
            ->assertJsonPath('meta.pagination.has_previous', true);
    }

    public function test_empty_catalog_has_one_empty_page(): void
    {
        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonPath('meta.pagination.total', 0)
            ->assertJsonPath('meta.pagination.last_page', 1)
            ->assertJsonPath('meta.pagination.has_next', false)
            ->assertJsonPath('meta.pagination.has_previous', false);
    }

    public function test_pagination_and_sort_validation_is_strict(): void
    {
        foreach (['page=0', 'page=-1', 'page=1.5', 'page=abc', 'per_page=0', 'per_page=101', 'per_page=-1', 'per_page=abc'] as $query) {
            $this->getJson('/api/v1/products?'.$query)->assertUnprocessable();
        }

        foreach (['sku', 'id', 'cost_price', 'inventory', 'deleted_at', 'random'] as $sort) {
            $this->getJson('/api/v1/products?sort='.$sort)->assertUnprocessable();
        }

        foreach (['ascending', 'descending', '1', '-1', 'random'] as $direction) {
            $this->getJson('/api/v1/products?sort_direction='.$direction)->assertUnprocessable();
        }
    }

    public function test_sorting_uses_defaults_explicit_directions_and_id_tie_breaking(): void
    {
        $category = Category::factory()->create();
        $first = Product::factory()->create(['category_id' => $category->id, 'name' => 'Same Name', 'slug' => 'same-name-first', 'created_at' => '2026-01-01 00:00:00']);
        $second = Product::factory()->create(['category_id' => $category->id, 'name' => 'Same Name', 'slug' => 'same-name-second', 'created_at' => '2026-01-01 00:00:00']);
        $newest = Product::factory()->create(['category_id' => $category->id, 'name' => 'Newest', 'created_at' => '2026-02-01 00:00:00']);
        $oldest = Product::factory()->create(['category_id' => $category->id, 'name' => 'Oldest', 'created_at' => '2025-01-01 00:00:00']);

        $this->getJson('/api/v1/products')
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($newest))
            ->assertJsonPath('data.1.id', ProductIdentifier::encode($first));

        $this->getJson('/api/v1/products?sort=created_at&sort_direction=asc')
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($oldest));

        $this->getJson('/api/v1/products?sort=name')
            ->assertJsonPath('data.0.name', 'Newest')
            ->assertJsonPath('data.1.name', 'Oldest')
            ->assertJsonPath('data.2.id', ProductIdentifier::encode($first))
            ->assertJsonPath('data.3.id', ProductIdentifier::encode($second));

        $this->getJson('/api/v1/products?sort=name&sort_direction=desc')
            ->assertJsonPath('data.0.name', 'Same Name')
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($first))
            ->assertJsonPath('data.1.id', ProductIdentifier::encode($second));
    }

    public function test_price_sorting_uses_the_same_persisted_base_price_as_the_response(): void
    {
        $category = Category::factory()->create();
        $expensive = Product::factory()->create(['category_id' => $category->id, 'slug' => 'expensive-price', 'price_amount' => 200]);
        $cheap = Product::factory()->create(['category_id' => $category->id, 'slug' => 'cheap-price', 'price_amount' => 100]);
        ProductVariant::factory()->create(['product_id' => $expensive->id, 'price_amount' => 300]);
        ProductVariant::factory()->create(['product_id' => $expensive->id, 'price_amount' => 200]);
        ProductVariant::factory()->create(['product_id' => $cheap->id, 'price_amount' => 100]);

        $this->getJson('/api/v1/products?sort=price&sort_direction=desc')
            ->assertJsonPath('data.0.slug', 'expensive-price')
            ->assertJsonPath('data.0.price.amount', 200)
            ->assertJsonPath('data.1.slug', 'cheap-price')
            ->assertJsonPath('data.1.price.amount', 100);
    }

    public function test_search_and_filters_are_applied_before_pagination_and_sorting(): void
    {
        $category = Category::factory()->create(['slug' => 'phase-56-dining']);
        $matching = Product::factory()->count(3)->create(['category_id' => $category->id]);
        foreach ($matching as $product) {
            $product->update(['name' => 'Oak Dining '.$product->id]);
            ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 100 + $product->id]);
        }
        foreach (range(1, 4) as $index) {
            Product::factory()->create(['category_id' => $category->id, 'name' => 'Pine Dining', 'slug' => 'pine-dining-'.$index]);
        }

        $this->getJson('/api/v1/products?search=oak&category=phase-56-dining&sort=name&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.has_previous', true)
            ->assertJsonPath('meta.pagination.has_next', false);
    }

    public function test_duplicate_variant_matches_count_once_across_pages(): void
    {
        $category = Category::factory()->create();
        $products = collect(range(1, 3))->map(fn (int $index) => Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Oak Collection',
            'slug' => 'oak-collection-'.$index,
        ]));
        foreach ($products as $product) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'OAK-'.$product->id.'-A']);
            ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'OAK-'.$product->id.'-B']);
        }

        $firstPage = $this->getJson('/api/v1/products?search=OAK&per_page=2&page=1')->assertOk();
        $secondPage = $this->getJson('/api/v1/products?search=OAK&per_page=2&page=2')->assertOk();

        $this->assertSame(3, $firstPage->json('meta.pagination.total'));
        $this->assertCount(2, $firstPage->json('data'));
        $this->assertCount(1, $secondPage->json('data'));
        $this->assertEmpty(array_intersect(
            array_column($firstPage->json('data'), 'id'),
            array_column($secondPage->json('data'), 'id'),
        ));
    }
}
