<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_is_public_paginated_and_excludes_structural_root_and_descendants(): void
    {
        $root = Category::factory()->create(['name' => 'Furnitures Root', 'slug' => 'furnitures-root', 'parent_id' => null]);
        $first = Category::factory()->create(['parent_id' => $root->id, 'display_order' => 1, 'name' => 'Living Room', 'slug' => 'living-room']);
        Category::factory()->create(['parent_id' => $root->id, 'display_order' => 2, 'name' => 'Bedroom', 'slug' => 'bedroom']);
        Category::factory()->create(['parent_id' => $first->id, 'name' => 'Sofas', 'slug' => 'sofas']);
        Category::factory()->inactive()->create(['parent_id' => $root->id, 'name' => 'Hidden', 'slug' => 'hidden']);

        $response = $this->getJson('/api/v1/categories?per_page=1');

        $response->assertOk()
            ->assertHeaderContains('Cache-Control', 'public')
            ->assertHeaderContains('Cache-Control', 'max-age=300')
            ->assertHeaderContains('Cache-Control', 's-maxage=600')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Living Room')
            ->assertJsonMissing(['name' => 'Furnitures Root'])
            ->assertJsonMissing(['name' => 'Sofas'])
            ->assertJsonMissing(['name' => 'Hidden']);

        $response->assertJsonStructure(['data' => [['id', 'name', 'slug', 'image']], 'meta' => ['pagination' => [
            'current_page', 'per_page', 'total', 'last_page', 'has_next', 'has_previous',
        ]]]);
    }

    public function test_collection_orders_by_display_order_and_id_and_supports_empty_beyond_last_page(): void
    {
        $root = Category::factory()->create(['slug' => 'furnitures-root']);
        Category::factory()->create(['parent_id' => $root->id, 'display_order' => 2, 'slug' => 'second']);
        Category::factory()->create(['parent_id' => $root->id, 'display_order' => 1, 'slug' => 'first']);

        $this->getJson('/api/v1/categories?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'second');

        $this->getJson('/api/v1/categories?per_page=1&page=9')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.has_previous', true);
    }

    public function test_detail_resolves_by_slug_and_opaque_id_with_allow_listed_shape(): void
    {
        $root = Category::factory()->create(['slug' => 'furnitures-root']);
        $category = Category::factory()->create([
            'parent_id' => $root->id,
            'slug' => 'living-room',
            'description' => 'Furniture for living spaces.',
            'image_url' => 'https://cdn.example.test/living-room.jpg',
        ]);

        foreach (['living-room', 'cat_'.base_convert((string) $category->id, 10, 36)] as $identifier) {
            $this->getJson('/api/v1/categories/'.$identifier)
                ->assertOk()
                ->assertHeaderContains('Cache-Control', 'public')
                ->assertHeaderContains('Cache-Control', 'max-age=300')
                ->assertHeaderContains('Cache-Control', 's-maxage=600')
                ->assertJsonPath('data.slug', 'living-room')
                ->assertJsonPath('data.description', 'Furniture for living spaces.')
                ->assertJsonPath('data.image.url', 'https://cdn.example.test/living-room.jpg')
                ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description', 'image', 'created_at']])
                ->assertJsonMissingPath('data.parent_id')
                ->assertJsonMissingPath('data.is_active');
        }
    }

    public function test_unknown_inactive_and_structural_categories_are_masked(): void
    {
        $root = Category::factory()->create(['slug' => 'furnitures-root']);
        $inactive = Category::factory()->inactive()->create(['parent_id' => $root->id, 'slug' => 'hidden']);

        foreach (['missing', 'hidden', 'furnitures-root', 'cat_'.base_convert((string) $inactive->id, 10, 36)] as $identifier) {
            $this->getJson('/api/v1/categories/'.$identifier)
                ->assertNotFound()
                ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_invalid_pagination_uses_canonical_validation_envelope(): void
    {
        $this->getJson('/api/v1/categories?page=0&per_page=101')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors', 'meta' => ['request_id']]);
    }
}
