<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CategoryIdentifier;
use App\Support\PermissionName;
use App\Support\ProductIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

class ProductManagementApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    private const SECOND_PRODUCT_NAME = 'Second Product';

    private const PRODUCTS_URL = '/api/v1/products';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_manage_staff_creates_variantless_product_with_persisted_base_price(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_manager']));

        $response = $this->withHeaders($headers)->postJson(self::PRODUCTS_URL, [
            'name' => 'Made to Order Oak Desk',
            'slug' => 'made-to-order-oak-desk',
            'description' => 'Built to your measurements.',
            'product_type' => 'MADE_TO_ORDER',
            'price' => ['amount' => 125000000, 'currency' => 'TZS'],
            'category_id' => 'cat_'.base_convert((string) $category->id, 10, 36),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.price.amount', 125000000)
            ->assertJsonPath('data.price.currency', 'TZS')
            ->assertJsonPath('data.product_type', 'MADE_TO_ORDER');

        $product = Product::query()->where('slug', 'made-to-order-oak-desk')->sole();
        $this->assertSame(125000000, $product->price_amount);
        $this->assertSame('TZS', $product->price_currency);
        $this->assertSame(0, ProductVariant::query()->where('product_id', $product->id)->count());
        $this->getJson(self::PRODUCTS_URL.'/'.ProductIdentifier::encode($product))
            ->assertOk()
            ->assertJsonPath('data.price.amount', 125000000);
    }

    public function test_product_update_changes_only_product_base_price(): void
    {
        $product = Product::factory()->create(['price_amount' => 100]);
        $firstVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 100]);
        $secondVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_amount' => 150]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_updater']));

        $this->withHeaders($headers)->patchJson(self::PRODUCTS_URL.'/'.ProductIdentifier::encode($product), [
            'price' => ['amount' => 120, 'currency' => 'TZS'],
            'is_published' => false,
        ])->assertOk()
            ->assertJsonPath('data.price.amount', 120)
            ->assertJsonPath('data.is_published', false);

        $this->assertSame(120, $product->fresh()->price_amount);
        $this->assertSame(100, $firstVariant->fresh()->price_amount);
        $this->assertSame(150, $secondVariant->fresh()->price_amount);
    }

    public function test_product_update_rejects_an_empty_price(): void
    {
        $product = Product::factory()->create(['price_amount' => 100]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'empty_price_rejector']));

        $this->withHeaders($headers)->patchJson(self::PRODUCTS_URL.'/'.ProductIdentifier::encode($product), [
            'price' => [],
        ])->assertUnprocessable();

        $this->assertSame(100, $product->fresh()->price_amount);
    }

    public function test_request_only_mode_blocks_in_stock_publication_on_create_and_update(): void
    {
        config(['commerce.request_only' => true]);
        $category = Category::factory()->create(['is_active' => true]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'request_only_manager']));
        $payload = [
            'name' => 'Available Chair',
            'slug' => 'available-chair',
            'product_type' => 'IN_STOCK',
            'price' => ['amount' => 100, 'currency' => 'TZS'],
            'category_id' => CategoryIdentifier::encode($category),
        ];

        $this->withHeaders($headers)->postJson(self::PRODUCTS_URL, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('errors.0.field', 'is_published');

        $draft = Product::factory()->create(['category_id' => $category->id, 'product_type' => 'IN_STOCK', 'is_published' => false]);
        $this->withHeaders($headers)->patchJson(self::PRODUCTS_URL.'/'.ProductIdentifier::encode($draft), ['is_published' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'BUSINESS_RULE_VIOLATION');

        $this->assertFalse($draft->fresh()->is_published);
    }

    public function test_product_management_rejects_the_structural_root_category(): void
    {
        $root = Category::factory()->create(['slug' => 'furnitures-root', 'parent_id' => null, 'is_active' => true]);
        $category = Category::factory()->create(['is_active' => true]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'root_category_rejector']));

        $this->withHeaders($headers)->postJson(self::PRODUCTS_URL, [
            'name' => 'Root Category Product',
            'slug' => 'root-category-product',
            'product_type' => 'MADE_TO_ORDER',
            'price' => ['amount' => 100, 'currency' => 'TZS'],
            'category_id' => 'furnitures-root',
        ])->assertNotFound()->assertJsonPath('errors.0.field', 'category_id');

        $product = Product::factory()->create(['category_id' => $category->id]);
        $this->withHeaders($headers)->patchJson(self::PRODUCTS_URL.'/'.ProductIdentifier::encode($product), [
            'category_id' => CategoryIdentifier::encode($root),
        ])->assertNotFound()->assertJsonPath('errors.0.field', 'category_id');

        $this->assertSame($category->id, $product->fresh()->category_id);
    }

    public function test_operational_reads_include_drafts_with_private_cache_headers(): void
    {
        $draft = Product::factory()->draft()->create(['slug' => 'operational-draft', 'price_amount' => 400]);
        ProductVariant::factory()->create(['product_id' => $draft->id, 'sku' => 'SECOND', 'display_order' => 2, 'price_amount' => 600, 'cost_price_amount' => 200, 'cost_price_currency' => 'TZS']);
        ProductVariant::factory()->create(['product_id' => $draft->id, 'sku' => 'FIRST', 'display_order' => 1, 'price_amount' => 600]);
        ProductVariant::factory()->create(['product_id' => $draft->id, 'sku' => 'TIED', 'display_order' => 1]);
        ProductVariant::factory()->inactive()->create(['product_id' => $draft->id, 'price_amount' => 700]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_reader']));

        $this->withHeaders($headers)->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($draft))
            ->assertJsonPath('data.0.price.amount', 400)
            ->assertJsonPath('data.0.is_published', false)
            ->assertJsonMissingPath('data.0.variants.0.cost_price_amount');

        $this->withHeaders($headers)->getJson('/api/v1/admin/products/'.$draft->slug)
            ->assertOk()
            ->assertJsonPath('data.variants.0.price.amount', 600)
            ->assertJsonPath('data.variants.0.sku', 'FIRST')
            ->assertJsonPath('data.variants.1.sku', 'TIED')
            ->assertJsonPath('data.variants.2.sku', 'SECOND')
            ->assertJsonCount(3, 'data.variants')
            ->assertJsonPath('data.inventory.quantity', 0);
    }

    public function test_operational_product_listing_filters_by_publication_state(): void
    {
        Product::factory()->create(['slug' => 'published-product', 'is_published' => true]);
        $draft = Product::factory()->draft()->create(['slug' => 'draft-product']);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_filter_reader']));

        $this->withHeaders($headers)->getJson('/api/v1/admin/products?is_published=false')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', ProductIdentifier::encode($draft));
    }

    public function test_operational_product_listing_accepts_numeric_pagination_query_strings(): void
    {
        Product::factory()->count(2)->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_pagination_reader']));

        $this->withHeaders($headers)->getJson('/api/v1/admin/products?page=2&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_operational_product_search_treats_like_wildcards_as_literal_characters(): void
    {
        Product::factory()->create(['slug' => 'first-product', 'name' => 'First Product']);
        Product::factory()->create(['slug' => 'second-product', 'name' => self::SECOND_PRODUCT_NAME]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_search_reader']));

        $this->withHeaders($headers)->getJson('/api/v1/admin/products?search=%25')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_operational_product_listing_reports_each_unknown_query_parameter(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_query_validator']));

        $this->withHeaders($headers)->getJson('/api/v1/admin/products?unexpected_filter=oak&unsupported_sort=oldest')
            ->assertUnprocessable()
            ->assertJsonCount(2, 'errors')
            ->assertJsonPath('errors.0.field', 'unexpected_filter')
            ->assertJsonPath('errors.1.field', 'unsupported_sort');
    }

    public function test_operational_read_permission_does_not_grant_product_mutation(): void
    {
        $product = Product::factory()->draft()->create();
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_viewer']));

        $this->withHeaders($headers)->getJson('/api/v1/admin/products/'.$product->slug)->assertOk();
        $this->withHeaders($headers)->patchJson(self::PRODUCTS_URL.'/'.$product->slug, ['name' => 'Changed'])->assertForbidden();
    }

    public function test_product_management_rejects_untrusted_payloads_and_inactive_categories(): void
    {
        $category = Category::factory()->create(['is_active' => false]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_validator']));
        $payload = [
            'name' => 'Invalid Product',
            'slug' => 'invalid-product',
            'product_type' => 'MADE_TO_ORDER',
            'price' => ['amount' => 100, 'currency' => 'TZS'],
            'category_id' => 'cat_'.base_convert((string) $category->id, 10, 36),
        ];

        $this->withHeaders($headers)->postJson(self::PRODUCTS_URL, [...$payload, 'variants' => []])
            ->assertUnprocessable();
        $this->withHeaders($headers)->postJson(self::PRODUCTS_URL, $payload)
            ->assertNotFound();
        $this->withHeaders($headers)->postJson(self::PRODUCTS_URL, [...$payload, 'price' => ['amount' => '100', 'currency' => 'TZS']])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('products', ['slug' => 'invalid-product']);
    }

    public function test_product_management_reports_slug_collisions_without_persisting_partial_update(): void
    {
        $product = Product::factory()->create(['slug' => 'first-product', 'name' => 'First Product']);
        $other = Product::factory()->create(['slug' => 'second-product', 'name' => self::SECOND_PRODUCT_NAME]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_slug_manager']));

        $this->withHeaders($headers)->patchJson(self::PRODUCTS_URL.'/'.ProductIdentifier::encode($other), [
            'name' => 'Changed Product',
            'slug' => $product->slug,
        ])->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CONFLICT')
            ->assertJsonPath('errors.0.field', 'slug');

        $this->assertSame(self::SECOND_PRODUCT_NAME, $other->fresh()->name);
    }

    public function test_product_management_rejects_a_slug_held_by_a_soft_deleted_product(): void
    {
        $deleted = Product::factory()->create(['slug' => 'retired-product']);
        $deleted->delete();
        $category = Category::factory()->create(['is_active' => true]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'soft_deleted_slug_manager']));

        $this->withHeaders($headers)->postJson(self::PRODUCTS_URL, [
            'name' => 'Replacement Product',
            'slug' => 'retired-product',
            'product_type' => 'MADE_TO_ORDER',
            'price' => ['amount' => 100, 'currency' => 'TZS'],
            'category_id' => 'cat_'.base_convert((string) $category->id, 10, 36),
        ])->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CONFLICT')
            ->assertJsonPath('errors.0.field', 'slug');
    }

    public function test_product_delete_route_does_not_exist(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'product_deleter']));

        $this->withHeaders($headers)->deleteJson(self::PRODUCTS_URL.'/'.ProductIdentifier::encode($product))->assertMethodNotAllowed();
    }

    public function test_product_slug_openapi_patterns_match_the_domain_invariant(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        foreach (['ProductCreateRequest', 'ProductUpdateRequest'] as $schema) {
            $this->assertSame('^[a-z0-9]+(?:-[a-z0-9]+)*$', $document['components']['schemas'][$schema]['properties']['slug']['pattern']);
        }
    }
}
