<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\SpaceType;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_exist_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('category_recommendations'));
    }

    public function test_public_fields_are_nullable_and_persisted(): void
    {
        $withoutPublicFields = Category::factory()->create();
        $withPublicFields = Category::factory()->create([
            'description' => 'Furniture for focused workspaces.',
            'image_url' => 'https://cdn.example.test/categories/workspace.jpg',
        ]);

        $this->assertTrue(Schema::hasColumns('categories', ['description', 'image_url']));
        $this->assertNull($withoutPublicFields->description);
        $this->assertNull($withoutPublicFields->image_url);
        $this->assertSame('Furniture for focused workspaces.', $withPublicFields->fresh()->description);
        $this->assertSame('https://cdn.example.test/categories/workspace.jpg', $withPublicFields->fresh()->image_url);
    }

    public function test_root_category_supports_null_parent_id(): void
    {
        $root = $this->createCategory(['slug' => 'root']);

        $this->assertNull($root->parent_id);
        $this->assertNull($root->parent);
        $this->assertDatabaseHas('categories', ['id' => $root->id, 'parent_id' => null]);
    }

    public function test_child_category_can_reference_parent(): void
    {
        $parent = $this->createCategory(['slug' => 'parent']);
        $child = $this->createCategory(['slug' => 'child'], $parent);

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue($child->parent->is($parent));
    }

    public function test_parent_deletion_nulls_child_parent_id(): void
    {
        $parent = $this->createCategory(['slug' => 'parent']);
        $child = $this->createCategory(['slug' => 'child'], $parent);

        $parent->delete();

        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => null]);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $this->createCategory(['slug' => 'shared-slug']);

        $this->expectException(QueryException::class);
        $this->createCategory(['slug' => 'shared-slug']);
    }

    public function test_valid_kebab_case_slug_is_accepted(): void
    {
        $category = $this->createCategory(['slug' => 'living-room']);

        $this->assertSame('living-room', $category->fresh()->slug);
    }

    public function test_slug_must_use_kebab_case(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A category slug must use kebab-case.');
        $this->createCategory(['slug' => 'Living Room']);
    }

    public function test_rejects_underscore_and_uppercase_slug(): void
    {
        $this->expectException(DomainException::class);
        $this->createCategory(['slug' => 'Living_Room']);
    }

    public function test_rejects_slug_with_trailing_hyphen(): void
    {
        $this->expectException(DomainException::class);
        $this->createCategory(['slug' => 'living-room-']);
    }

    public function test_rejects_slug_with_leading_hyphen(): void
    {
        $this->expectException(DomainException::class);
        $this->createCategory(['slug' => '-living-room']);
    }

    public function test_rejects_slug_with_consecutive_hyphens(): void
    {
        $this->expectException(DomainException::class);
        $this->createCategory(['slug' => 'living--room']);
    }

    public function test_rejects_empty_slug(): void
    {
        $this->expectException(DomainException::class);
        $this->createCategory(['slug' => '']);
    }

    public function test_recommendation_category_id_foreign_key_is_enforced(): void
    {
        $target = $this->createCategory(['slug' => 'target']);

        $this->expectException(QueryException::class);
        DB::table('category_recommendations')->insert([
            'category_id' => 999999,
            'recommended_category_id' => $target->id,
            'relation_type' => 'COMPLETE_THE_LOOK',
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_recommendation_recommended_category_id_foreign_key_is_enforced(): void
    {
        $source = $this->createCategory(['slug' => 'source']);

        $this->expectException(QueryException::class);
        DB::table('category_recommendations')->insert([
            'category_id' => $source->id,
            'recommended_category_id' => 999999,
            'relation_type' => 'COMPLETE_THE_LOOK',
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_recommendation_pair_uniqueness_is_enforced(): void
    {
        $source = $this->createCategory(['slug' => 'source']);
        $target = $this->createCategory(['slug' => 'target']);

        DB::table('category_recommendations')->insert([
            'category_id' => $source->id,
            'recommended_category_id' => $target->id,
            'relation_type' => 'COMPLETE_THE_LOOK',
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('category_recommendations')->insert([
            'category_id' => $source->id,
            'recommended_category_id' => $target->id,
            'relation_type' => 'PAIR_WITH',
            'priority' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cascade_delete_removes_recommendations_on_source_side(): void
    {
        $source = $this->createCategory(['slug' => 'source']);
        $target = $this->createCategory(['slug' => 'target']);
        $source->recommendedCategories()->attach($target->id, [
            'relation_type' => 'COMPLETE_THE_LOOK',
            'priority' => 1,
        ]);

        $source->delete();

        $this->assertSame(0, DB::table('category_recommendations')->where('category_id', $source->id)->count());
        $this->assertSame(0, DB::table('category_recommendations')->count());
    }

    public function test_cascade_delete_removes_recommendations_on_target_side(): void
    {
        $source = $this->createCategory(['slug' => 'source']);
        $target = $this->createCategory(['slug' => 'target']);
        $source->recommendedCategories()->attach($target->id, [
            'relation_type' => 'COMPLETE_THE_LOOK',
            'priority' => 1,
        ]);

        $target->delete();

        $this->assertSame(0, DB::table('category_recommendations')->where('recommended_category_id', $target->id)->count());
        $this->assertSame(0, DB::table('category_recommendations')->count());
    }

    public function test_down_methods_drop_tables_cleanly(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('category_recommendations'));

        Schema::dropIfExists('category_recommendations');
        Schema::dropIfExists('categories');

        $this->assertFalse(Schema::hasTable('categories'));
        $this->assertFalse(Schema::hasTable('category_recommendations'));
    }

    private function createCategory(array $attributes = [], ?Category $parent = null): Category
    {
        $category = new Category([
            'name' => $attributes['name'] ?? 'Category',
            'slug' => $attributes['slug'] ?? Str::random(10),
            'space_type' => $attributes['space_type'] ?? SpaceType::HOME->value,
            'display_order' => $attributes['display_order'] ?? 0,
        ]);
        $category->parent_id = $parent?->id;
        $category->save();

        return $category->fresh();
    }
}
