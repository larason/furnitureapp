<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Support\CatalogAvailability;
use App\Support\ProductIdentifier;
use App\Support\ProductType;
use App\Support\VariantIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 5.11 Group E closure regressions: permanent cross-phase guarantees that
 * later refactors must not break. Detailed behavior lives in the per-phase
 * suites; this class consolidates the exit-gate invariants.
 */
class GroupEContractRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_variant_attributes_do_not_surface_a_product(): void
    {
        $category = Category::factory()->create();
        $inactiveOnly = Product::factory()->create(['category_id' => $category->id, 'name' => 'Plain Bench', 'slug' => 'plain-bench']);
        ProductVariant::factory()->inactive()->create([
            'product_id' => $inactiveOnly->id,
            'sku' => 'PLAIN-BENCH-SKU',
            'attributes' => ['color' => 'Hidden Teal', 'material' => 'Velvet'],
        ]);

        $active = Product::factory()->create(['category_id' => $category->id, 'name' => 'Visible Bench', 'slug' => 'visible-bench']);
        ProductVariant::factory()->create([
            'product_id' => $active->id,
            'attributes' => ['color' => 'Teal Green'],
        ]);

        $this->getJson('/api/v1/products?search='.urlencode('teal'))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($active))
            ->assertJsonMissing(['slug' => 'plain-bench']);

        $this->getJson('/api/v1/products?search=PLAIN-BENCH-SKU')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_group_e_route_surface_is_registered_exactly(): void
    {
        $expected = [
            'api.categories.index' => ['GET', 'api/v1/categories'],
            'api.categories.show' => ['GET', 'api/v1/categories/{category}'],
            'api.products.index' => ['GET', 'api/v1/products'],
            'api.products.show' => ['GET', 'api/v1/products/{product}'],
            'api.products.variants.index' => ['GET', 'api/v1/products/{product}/variants'],
            'api.products.variants.show' => ['GET', 'api/v1/products/{product}/variants/{variant}'],
            'api.inventory.index' => ['GET', 'api/v1/inventory'],
            'api.inventory.show' => ['GET', 'api/v1/inventory/{inventory}'],
            'api.inventory.adjust' => ['POST', 'api/v1/inventory/{inventory}/adjust'],
        ];

        foreach ($expected as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route {$name}.");
            $this->assertContains($method, $route->methods(), "Route {$name} method mismatch.");
            $this->assertSame($uri, $route->uri(), "Route {$name} uri mismatch.");
        }
    }

    public function test_rejected_catalog_routes_are_not_registered(): void
    {
        $this->getJson('/api/v1/search')->assertNotFound();
        $this->getJson('/api/v1/variants')->assertNotFound();
        $this->getJson('/api/v1/categories/demo-category/products')->assertNotFound();
        $this->getJson('/api/v1/products/demo-product/images')->assertStatus(405);

        $this->patchJson('/api/v1/inventory/inv_1')->assertStatus(405);
        $this->deleteJson('/api/v1/inventory/inv_1')->assertStatus(405);
    }

    public function test_low_stock_boundaries_follow_the_centralized_threshold(): void
    {
        $cases = [
            ['available' => 0, 'availability' => 'unavailable', 'indicator' => 'IN_STOCK'],
            ['available' => 1, 'availability' => 'available', 'indicator' => 'LOW_STOCK'],
            ['available' => CatalogAvailability::LOW_STOCK_THRESHOLD, 'availability' => 'available', 'indicator' => 'LOW_STOCK'],
            ['available' => CatalogAvailability::LOW_STOCK_THRESHOLD + 1, 'availability' => 'available', 'indicator' => 'IN_STOCK'],
        ];

        foreach ($cases as $index => $case) {
            $category = Category::factory()->create(['is_active' => true]);
            $product = Product::factory()->create([
                'category_id' => $category->id,
                'slug' => 'boundary-product-'.$index,
                'product_type' => ProductType::IN_STOCK,
                'is_active' => true,
                'is_published' => true,
            ]);
            $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
            ProductStock::factory()->forVariant($variant)->create([
                'warehouse_location' => 'main',
                'quantity' => $case['available'],
                'reserved_quantity' => 0,
            ]);

            $this->getJson('/api/v1/products/'.$product->slug)
                ->assertOk()
                ->assertJsonPath('data.availability', $case['availability'])
                ->assertJsonPath('data.stock_indicator', $case['indicator']);
        }
    }

    public function test_public_catalog_endpoints_never_expose_operational_inventory_fields(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'leak-check-sofa',
            'product_type' => ProductType::IN_STOCK,
            'is_active' => true,
            'is_published' => true,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($variant)->create([
            'warehouse_location' => 'dar-es-salaam',
            'quantity' => 9,
            'reserved_quantity' => 4,
        ]);

        $paths = [
            '/api/v1/products',
            '/api/v1/products/'.$product->slug,
            '/api/v1/products/'.$product->slug.'/variants',
            '/api/v1/products/'.$product->slug.'/variants/'.VariantIdentifier::encode($variant),
        ];

        foreach ($paths as $path) {
            $response = $this->getJson($path)->assertOk();

            $response->assertJsonMissingPath('data.0.quantity');
            $response->assertJsonMissingPath('data.quantity');
            $response->assertJsonMissingPath('data.0.reserved_quantity');
            $response->assertJsonMissingPath('data.reserved_quantity');
            $response->assertJsonMissingPath('data.0.available_quantity');
            $response->assertJsonMissingPath('data.available_quantity');
            $response->assertJsonMissing(['warehouse_location' => 'dar-es-salaam']);
            $this->assertStringNotContainsString('dar-es-salaam', (string) $response->getContent());
        }
    }
}
