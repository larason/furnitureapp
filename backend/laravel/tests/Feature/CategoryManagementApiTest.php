<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CategoryIdentifier;
use App\Support\PermissionName;
use App\Support\SpaceType;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

class CategoryManagementApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_category_creation_requires_products_manage_permission(): void
    {
        $payload = $this->createPayload();

        $this->postJson('/api/v1/categories', $payload)->assertUnauthorized();
        $this->withHeaders($this->authenticateAs(User::factory()->customer()->create(['clerk_user_id' => 'category_customer'])))
            ->postJson('/api/v1/categories', $payload)->assertForbidden();

        Role::findByName('STAFF')->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $this->withHeaders($this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_staff_denied'])))
            ->postJson('/api/v1/categories', $payload)->assertForbidden();

        Role::findByName('ADMIN')->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $this->withHeaders($this->authenticateAs(User::factory()->admin()->create(['clerk_user_id' => 'category_admin_denied'])))
            ->postJson('/api/v1/categories', $payload)->assertForbidden();
    }

    public function test_staff_and_admin_with_products_manage_can_create_categories(): void
    {
        $this->createRoot();
        $staffHeaders = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_staff_manager']));
        $adminHeaders = $this->authenticateAs(User::factory()->admin()->create(['clerk_user_id' => 'category_admin_manager']));

        $this->withHeaders($staffHeaders)->postJson('/api/v1/categories', $this->createPayload('Staff Category', 'staff-category'))->assertCreated();
        $this->withHeaders($adminHeaders)->postJson('/api/v1/categories', $this->createPayload('Admin Category', 'admin-category'))->assertCreated();
    }

    public function test_create_places_active_hybrid_category_after_root_children_and_exposes_it_publicly(): void
    {
        $root = $this->createRoot();
        Category::factory()->create(['parent_id' => $root->id, 'display_order' => 7, 'slug' => 'existing-category']);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_creator']));

        $this->withHeaders($headers)->postJson('/api/v1/categories', $this->createPayload('  Outdoor Living  ', '  outdoor-living  ', 'Outdoor furniture.', 'https://cdn.example.test/outdoor.jpg'))
            ->assertCreated()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.name', 'Outdoor Living')
            ->assertJsonPath('data.slug', 'outdoor-living')
            ->assertJsonPath('data.image.url', 'https://cdn.example.test/outdoor.jpg');

        $created = Category::query()->where('slug', 'outdoor-living')->sole();
        $this->assertSame($root->id, $created->parent_id);
        $this->assertSame(SpaceType::HYBRID, $created->space_type);
        $this->assertSame(8, $created->display_order);
        $this->assertTrue($created->is_active);

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonFragment(['slug' => 'outdoor-living']);
        $this->getJson('/api/v1/categories/'.CategoryIdentifier::encode($created))
            ->assertOk()
            ->assertJsonPath('data.description', 'Outdoor furniture.');
    }

    public function test_create_uses_one_as_the_first_display_order_and_fails_when_root_is_missing(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_root_requirement']));
        $this->withHeaders($headers)->postJson('/api/v1/categories', $this->createPayload())->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $root = $this->createRoot();
        $this->withHeaders($headers)->postJson('/api/v1/categories', $this->createPayload())->assertCreated();

        $this->assertDatabaseHas('categories', ['slug' => 'outdoor-living', 'parent_id' => $root->id, 'display_order' => 1]);
    }

    public function test_create_rejects_invalid_or_server_controlled_fields_without_persisting(): void
    {
        $this->createRoot();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_validation']));

        $this->withHeaders($headers)->postJson('/api/v1/categories', [
            ...$this->createPayload('Outdoor Living', 'outdoor--living'),
            'image' => 'not a uri',
            'parent_id' => 'cat_1',
            'space_type' => 'home',
            'display_order' => 99,
            'is_active' => false,
            'recommendations' => [],
        ])->assertUnprocessable()
            ->assertJsonCount(7, 'errors');

        $this->assertDatabaseMissing('categories', ['slug' => 'outdoor--living']);
    }

    public function test_update_resolves_inactive_categories_by_opaque_id_and_updates_content_only(): void
    {
        $root = $this->createRoot();
        $category = Category::factory()->inactive()->create([
            'parent_id' => $root->id,
            'slug' => 'seasonal',
            'name' => 'Seasonal',
            'description' => 'Old description',
            'image_url' => 'https://cdn.example.test/seasonal.jpg',
            'space_type' => SpaceType::HOME,
            'display_order' => 4,
        ]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_updater']));

        $this->withHeaders($headers)->patchJson('/api/v1/categories/'.CategoryIdentifier::encode($category), [
            'name' => '  Updated Seasonal  ',
            'slug' => '  updated-seasonal  ',
            'description' => null,
            'image' => null,
        ])->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('data.slug', 'updated-seasonal')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.image', null);

        $updated = $category->fresh();
        $this->assertSame('Updated Seasonal', $updated->name);
        $this->assertSame($root->id, $updated->parent_id);
        $this->assertSame(SpaceType::HOME, $updated->space_type);
        $this->assertSame(4, $updated->display_order);
        $this->assertFalse($updated->is_active);
    }

    public function test_update_validation_is_atomic_and_rejects_graph_mutation_fields(): void
    {
        $root = $this->createRoot();
        $category = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'unchanged', 'name' => 'Unchanged', 'display_order' => 4]);
        $target = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'target']);
        $category->recommendedCategories()->attach($target->id, ['relation_type' => 'PAIR_WITH', 'priority' => 1]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_graph_guard']));

        $this->withHeaders($headers)->patchJson('/api/v1/categories/unchanged', [
            'name' => 'Would Change',
            'slug' => 'invalid--slug',
            'parent_id' => CategoryIdentifier::encode($target),
            'display_order' => 20,
            'is_active' => false,
            'space_type' => 'office',
            'recommendations' => [],
        ])->assertUnprocessable();

        $fresh = $category->fresh();
        $this->assertSame('Unchanged', $fresh->name);
        $this->assertSame('unchanged', $fresh->slug);
        $this->assertSame($root->id, $fresh->parent_id);
        $this->assertSame(4, $fresh->display_order);
        $this->assertSame([$target->id], $fresh->recommendedCategories()->pluck('categories.id')->all());
    }

    public function test_update_maps_slug_collisions_and_protects_the_structural_root(): void
    {
        $root = $this->createRoot();
        $first = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'first-category']);
        $second = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'second-category', 'name' => 'Second Category']);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_collision']));

        $this->withHeaders($headers)->patchJson('/api/v1/categories/second-category', [
            'name' => 'Changed Category',
            'slug' => $first->slug,
        ])->assertConflict()
            ->assertJsonPath('errors.0.code', 'CONFLICT')
            ->assertJsonPath('errors.0.field', 'slug');
        $this->assertSame('Second Category', $second->fresh()->name);

        $this->withHeaders($headers)->patchJson('/api/v1/categories/'.CategoryIdentifier::encode($root), ['name' => 'Broken Root'])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_products_continue_to_filter_by_category_after_its_slug_changes(): void
    {
        $root = $this->createRoot();
        $category = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'old-category']);
        $product = Product::factory()->create(['category_id' => $category->id, 'slug' => 'linked-product']);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'category_product_link']));

        $this->withHeaders($headers)->patchJson('/api/v1/categories/old-category', ['slug' => 'new-category'])->assertOk();

        $this->getJson('/api/v1/products?category=new-category')->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug);
        $this->getJson('/api/v1/products?category=old-category')->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_category_slug_openapi_patterns_match_the_domain_invariant_and_no_delete_route_exists(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        foreach (['CategoryCreateRequest', 'CategoryUpdateRequest'] as $schema) {
            $this->assertSame('^[a-z0-9]+(?:-[a-z0-9]+)*$', $document['components']['schemas'][$schema]['properties']['slug']['pattern']);
        }

        $this->deleteJson('/api/v1/categories/anything')->assertMethodNotAllowed();
    }

    /** @return array{name: string, slug: string, description: ?string, image: ?string} */
    private function createPayload(string $name = 'Outdoor Living', string $slug = 'outdoor-living', ?string $description = null, ?string $image = null): array
    {
        return compact('name', 'slug', 'description', 'image');
    }

    private function createRoot(): Category
    {
        return Category::factory()->create(['name' => 'Furnitures Root', 'slug' => 'furnitures-root', 'parent_id' => null, 'display_order' => 1]);
    }
}
