<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\InventoryIdentifier;
use App\Support\PermissionName;
use App\Support\ProductIdentifier;
use App\Support\RoleName;
use App\Support\VariantIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

class InventoryReadApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    private const INDEX = '/api/v1/inventory';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_anonymous_requests_are_rejected_with_authentication_error(): void
    {
        $this->getJson(self::INDEX)
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
        $this->getJson(self::INDEX.'/inv_1')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_customer_cannot_read_operational_inventory(): void
    {
        $stock = ProductStock::factory()->create();
        $headers = $this->authenticateAs(User::factory()->customer()->create(['clerk_user_id' => 'customer_1']));

        $this->withHeaders($headers)->getJson(self::INDEX)->assertForbidden();
        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))->assertForbidden();
    }

    public function test_staff_with_inventory_view_permission_can_read_inventory(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 12, 'reserved_quantity' => 3]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX)->assertOk();
        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))
            ->assertOk()
            ->assertJsonPath('data.quantity', 12);
    }

    public function test_staff_without_inventory_view_permission_is_forbidden(): void
    {
        $stock = ProductStock::factory()->create();
        Role::findByName(RoleName::STAFF->value)->revokePermissionTo(PermissionName::INVENTORY_VIEW->value);
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX)->assertForbidden();
        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))->assertForbidden();
    }

    public function test_admin_with_seeded_permission_can_read_inventory(): void
    {
        ProductStock::factory()->create();
        $headers = $this->authenticateAs(User::factory()->admin()->create(['clerk_user_id' => 'admin_1']));

        $this->withHeaders($headers)->getJson(self::INDEX)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_collection_returns_allow_listed_paginated_inventory(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 4]);
        $variant = $stock->productVariant;
        $headers = $this->authenticateAs($this->createStaff());

        $response = $this->withHeaders($headers)->getJson(self::INDEX)
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.0.id', InventoryIdentifier::encode($stock))
            ->assertJsonPath('data.0.product_id', ProductIdentifier::encode($variant->product))
            ->assertJsonPath('data.0.variant_id', VariantIdentifier::encode($variant))
            ->assertJsonPath('data.0.warehouse_location', ProductStock::DEFAULT_LOCATION)
            ->assertJsonPath('data.0.quantity', 10)
            ->assertJsonPath('data.0.reserved_quantity', 4)
            ->assertJsonPath('data.0.available_quantity', 6)
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.product_variant_id')
            ->assertJsonMissingPath('data.0.product')
            ->assertJsonMissingPath('data.0.variant')
            ->assertJsonMissingPath('links')
            ->assertJsonMissingPath('meta.links');

        $response->assertJsonStructure([
            'data' => [[
                'id', 'product_id', 'variant_id', 'warehouse_location',
                'quantity', 'reserved_quantity', 'available_quantity', 'updated_at',
            ]],
            'meta' => ['pagination' => [
                'current_page', 'per_page', 'total', 'last_page', 'has_next', 'has_previous',
            ]],
        ]);
    }

    public function test_collection_orders_by_updated_at_descending_then_id_ascending(): void
    {
        $older = ProductStock::factory()->create();
        $firstAtSameTime = ProductStock::factory()->create();
        $secondAtSameTime = ProductStock::factory()->create();
        DB::table('product_stocks')->where('id', $older->id)->update(['updated_at' => '2099-01-01 00:00:00']);
        DB::table('product_stocks')->where('id', $firstAtSameTime->id)->update(['updated_at' => '2099-01-02 00:00:00']);
        DB::table('product_stocks')->where('id', $secondAtSameTime->id)->update(['updated_at' => '2099-01-02 00:00:00']);
        $headers = $this->authenticateAs($this->createStaff());

        $response = $this->withHeaders($headers)->getJson(self::INDEX)->assertOk();

        $this->assertSame(
            [
                InventoryIdentifier::encode($firstAtSameTime),
                InventoryIdentifier::encode($secondAtSameTime),
                InventoryIdentifier::encode($older),
            ],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_variant_without_stock_does_not_produce_a_fabricated_zero_stock_row(): void
    {
        $stock = ProductStock::factory()->create();
        $variantWithoutStock = ProductVariant::factory()->create();
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', InventoryIdentifier::encode($stock));
        $this->withHeaders($headers)->getJson(self::INDEX.'?variant='.VariantIdentifier::encode($variantWithoutStock))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_pagination_contract_and_empty_beyond_last_page(): void
    {
        ProductStock::factory()->count(5)->create();
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX)
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonPath('meta.pagination.total', 5);

        $this->withHeaders($headers)->getJson(self::INDEX.'?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 5)
            ->assertJsonPath('meta.pagination.last_page', 3)
            ->assertJsonPath('meta.pagination.has_next', true)
            ->assertJsonPath('meta.pagination.has_previous', true);

        $this->withHeaders($headers)->getJson(self::INDEX.'?per_page=2&page=9')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.current_page', 3)
            ->assertJsonPath('meta.pagination.last_page', 3)
            ->assertJsonPath('meta.pagination.has_next', false)
            ->assertJsonPath('meta.pagination.has_previous', true);
    }

    public function test_empty_inventory_has_one_empty_page(): void
    {
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonPath('meta.pagination.total', 0)
            ->assertJsonPath('meta.pagination.last_page', 1)
            ->assertJsonPath('meta.pagination.has_next', false)
            ->assertJsonPath('meta.pagination.has_previous', false);
    }

    public function test_pagination_and_filter_validation_is_strict(): void
    {
        $headers = $this->authenticateAs($this->createStaff());

        foreach ([
            'page=0', 'page=1.5', 'page=abc',
            'per_page=0', 'per_page=101', 'per_page=abc',
            'sort=quantity', 'reserved_quantity_gt=0', 'variant=SKU-1',
        ] as $query) {
            $this->withHeaders($headers)->getJson(self::INDEX.'?'.$query)
                ->assertUnprocessable()
                ->assertJsonStructure(['errors', 'meta' => ['request_id']]);
        }
    }

    public function test_collection_filters_by_product_variant_and_location(): void
    {
        $first = ProductStock::factory()->atLocation('main')->create();
        $second = ProductStock::factory()->atLocation('dar-es-salaam')->create();
        $firstProduct = $first->productVariant->product;
        $secondVariant = $second->productVariant;
        $headers = $this->authenticateAs($this->createStaff());

        foreach ([
            'product='.$firstProduct->slug,
            'product='.$firstProduct->id,
            'product='.ProductIdentifier::encode($firstProduct),
        ] as $query) {
            $this->withHeaders($headers)->getJson(self::INDEX.'?'.$query)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', InventoryIdentifier::encode($first));
        }

        $this->withHeaders($headers)->getJson(self::INDEX.'?variant='.VariantIdentifier::encode($secondVariant))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', InventoryIdentifier::encode($second));

        $this->withHeaders($headers)->getJson(self::INDEX.'?warehouse_location=dar-es-salaam')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', InventoryIdentifier::encode($second));

        $this->withHeaders($headers)->getJson(self::INDEX.'?warehouse_location=unknown')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_detail_returns_exact_inventory_row(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 5, 'reserved_quantity' => 5]);
        $other = ProductStock::factory()->create();
        $headers = $this->authenticateAs($this->createStaff());

        $response = $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))
            ->assertOk()
            ->assertHeaderContains('Vary', 'Authorization')
            ->assertJsonPath('data.id', InventoryIdentifier::encode($stock))
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.reserved_quantity', 5)
            ->assertJsonPath('data.available_quantity', 0)
            ->assertJsonMissing(['id' => InventoryIdentifier::encode($other)]);

        $this->assertSame([
            'id', 'product_id', 'variant_id', 'warehouse_location',
            'quantity', 'reserved_quantity', 'available_quantity', 'updated_at',
        ], array_keys($response->json('data')));
    }

    public function test_unknown_or_cross_resource_identifier_is_not_found(): void
    {
        $stock = ProductStock::factory()->create();
        $variant = $stock->productVariant;
        $headers = $this->authenticateAs($this->createStaff());

        foreach ([
            'inv_missing',
            (string) $stock->id,
            VariantIdentifier::encode($variant),
            ProductIdentifier::encode($variant->product),
        ] as $identifier) {
            $this->withHeaders($headers)->getJson(self::INDEX.'/'.$identifier)
                ->assertNotFound()
                ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_multi_location_rows_remain_distinct_resources(): void
    {
        $variant = ProductVariant::factory()->create();
        $main = ProductStock::factory()->forVariant($variant)->atLocation('main')->create();
        $dar = ProductStock::factory()->forVariant($variant)->atLocation('dar-es-salaam')->create();
        $headers = $this->authenticateAs($this->createStaff());

        $response = $this->withHeaders($headers)->getJson(self::INDEX.'?variant='.VariantIdentifier::encode($variant))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSameCanonicalizeEqual(
            [InventoryIdentifier::encode($main), InventoryIdentifier::encode($dar)],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_available_quantity_is_derived_across_zero_and_fully_reserved_rows(): void
    {
        $zero = ProductStock::factory()->outOfStock()->create();
        $full = ProductStock::factory()->create(['quantity' => 8, 'reserved_quantity' => 8]);
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($zero))
            ->assertOk()
            ->assertJsonPath('data.quantity', 0)
            ->assertJsonPath('data.reserved_quantity', 0)
            ->assertJsonPath('data.available_quantity', 0);

        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($full))
            ->assertOk()
            ->assertJsonPath('data.quantity', 8)
            ->assertJsonPath('data.reserved_quantity', 8)
            ->assertJsonPath('data.available_quantity', 0);
    }

    public function test_inactive_unpublished_and_soft_deleted_product_stock_remains_visible(): void
    {
        $category = Category::factory()->create();
        $hidden = Product::factory()->inactive()->draft()->create(['category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $hidden->id]);
        $stock = ProductStock::factory()->forVariant($variant)->create();
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))
            ->assertOk()
            ->assertJsonPath('data.product_id', ProductIdentifier::encode($hidden));

        $hidden->delete();

        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))
            ->assertOk()
            ->assertJsonPath('data.product_id', ProductIdentifier::encode($hidden));
    }

    public function test_inactive_variant_stock_remains_operationally_visible_in_list_and_detail(): void
    {
        $variant = ProductVariant::factory()->inactive()->create();
        $stock = ProductStock::factory()->forVariant($variant)->create();
        $headers = $this->authenticateAs($this->createStaff());

        $this->withHeaders($headers)->getJson(self::INDEX.'?variant='.VariantIdentifier::encode($variant))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', InventoryIdentifier::encode($stock));
        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))
            ->assertOk()
            ->assertJsonPath('data.variant_id', VariantIdentifier::encode($variant));
    }

    public function test_inventory_responses_are_private_and_do_not_mutate_stock(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 9, 'reserved_quantity' => 2]);
        $headers = $this->authenticateAs($this->createStaff());
        $before = DB::table('product_stocks')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->withHeaders($headers)->getJson(self::INDEX)
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Vary', 'Authorization');
        $this->withHeaders($headers)->getJson(self::INDEX.'/'.InventoryIdentifier::encode($stock))
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store');

        $after = DB::table('product_stocks')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->assertSame($before, $after);
    }

    public function test_public_catalog_does_not_leak_operational_inventory(): void
    {
        $stock = ProductStock::factory()->create(['quantity' => 12, 'reserved_quantity' => 3]);
        $product = $stock->productVariant->product;

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonMissing(['warehouse_location' => ProductStock::DEFAULT_LOCATION])
            ->assertJsonMissingPath('data.0.quantity')
            ->assertJsonMissingPath('data.0.reserved_quantity')
            ->assertJsonMissingPath('data.0.available_quantity');

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonMissing(['warehouse_location' => ProductStock::DEFAULT_LOCATION])
            ->assertJsonMissingPath('data.quantity')
            ->assertJsonMissingPath('data.variants.0.quantity')
            ->assertJsonMissingPath('data.variants.0.reserved_quantity')
            ->assertJsonMissingPath('data.variants.0.available_quantity');
    }

    public function test_collection_avoids_variant_and_product_n_plus_one_queries(): void
    {
        ProductStock::factory()->count(3)->create();
        $headers = $this->authenticateAs($this->createStaff());
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->withHeaders($headers)->getJson(self::INDEX)->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(1, count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "product_variants"'))));
        $this->assertSame(0, count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "products"'))));
    }

    private function createStaff(): User
    {
        return User::factory()->staff()->create(['clerk_user_id' => 'staff_1']);
    }

    private function assertSameCanonicalizeEqual(array $expected, array $actual): void
    {
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }
}
