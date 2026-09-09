<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\CategoryRecommendationType;
use App\Support\SpaceType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommended_categories_returns_directed_targets(): void
    {
        [$sofas, $coffee] = $this->twoCategories();
        $sofas->recommendedCategories()->attach($coffee->id, [
            'relation_type' => CategoryRecommendationType::COMPLETE_THE_LOOK->value,
            'priority' => 5,
        ]);

        $recommended = $sofas->fresh()->recommendedCategories;

        $this->assertCount(1, $recommended);
        $this->assertTrue($recommended->first()->is($coffee));
    }

    public function test_inverse_recommendation_returns_recommended_by(): void
    {
        [$sofas, $coffee] = $this->twoCategories();
        $sofas->recommendedCategories()->attach($coffee->id, [
            'relation_type' => CategoryRecommendationType::COMPLETE_THE_LOOK->value,
            'priority' => 5,
        ]);

        $recommendedBy = $coffee->fresh()->recommendedByCategories;

        $this->assertCount(1, $recommendedBy);
        $this->assertTrue($recommendedBy->first()->is($sofas));
    }

    public function test_pivot_relation_type_and_priority_are_available(): void
    {
        [$sofas, $coffee] = $this->twoCategories();
        $sofas->recommendedCategories()->attach($coffee->id, [
            'relation_type' => CategoryRecommendationType::PAIR_WITH->value,
            'priority' => 3,
        ]);

        $pivot = $sofas->fresh()->recommendedCategories->first()->pivot;

        $this->assertSame(CategoryRecommendationType::PAIR_WITH->value, $pivot->relation_type);
        $this->assertSame(3, $pivot->priority);
    }

    public function test_priority_ordering_is_deterministic(): void
    {
        [$source, $high, $mid, $low] = $this->categories(4);
        $source->recommendedCategories()->attach($low->id, ['relation_type' => 'COMPLETE_THE_LOOK', 'priority' => 1]);
        $source->recommendedCategories()->attach($high->id, ['relation_type' => 'COMPLETE_THE_LOOK', 'priority' => 5]);
        $source->recommendedCategories()->attach($mid->id, ['relation_type' => 'COMPLETE_THE_LOOK', 'priority' => 3]);

        $ordered = $source->fresh()->recommendedCategories->pluck('slug')->all();

        $this->assertSame([$high->slug, $mid->slug, $low->slug], $ordered);
    }

    public function test_inverse_priority_ordering_is_deterministic(): void
    {
        [$target, $high, $mid, $low] = $this->categories(4);
        $low->recommendedCategories()->attach($target->id, ['relation_type' => 'COMPLETE_THE_LOOK', 'priority' => 1]);
        $high->recommendedCategories()->attach($target->id, ['relation_type' => 'COMPLETE_THE_LOOK', 'priority' => 5]);
        $mid->recommendedCategories()->attach($target->id, ['relation_type' => 'COMPLETE_THE_LOOK', 'priority' => 3]);

        $ordered = $target->fresh()->recommendedByCategories->pluck('slug')->all();

        $this->assertSame([$high->slug, $mid->slug, $low->slug], $ordered);
    }

    public function test_duplicate_pair_is_rejected(): void
    {
        [$source, $target] = $this->twoCategories();
        $source->recommendedCategories()->attach($target->id, [
            'relation_type' => 'COMPLETE_THE_LOOK',
            'priority' => 1,
        ]);

        $this->expectException(QueryException::class);
        $source->recommendedCategories()->attach($target->id, [
            'relation_type' => 'PAIR_WITH',
            'priority' => 2,
        ]);
    }

    public function test_relation_type_outside_closed_vocabulary_is_rejected(): void
    {
        [$source, $target] = $this->twoCategories();

        $this->expectException(QueryException::class);
        $source->recommendedCategories()->attach($target->id, [
            'relation_type' => 'UPSELL',
            'priority' => 1,
        ]);
    }

    public function test_relation_type_lowercase_variant_is_rejected(): void
    {
        [$source, $target] = $this->twoCategories();

        $this->expectException(QueryException::class);
        $source->recommendedCategories()->attach($target->id, [
            'relation_type' => 'pair_with',
            'priority' => 1,
        ]);
    }

    public function test_relation_type_via_direct_insert_is_rejected(): void
    {
        [$source, $target] = $this->twoCategories();

        $this->expectException(QueryException::class);
        DB::table('category_recommendations')->insert([
            'category_id' => $source->id,
            'recommended_category_id' => $target->id,
            'relation_type' => 'UPSELL',
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function twoCategories(): array
    {
        return $this->categories(2);
    }

    private function categories(int $count): array
    {
        $made = [];
        for ($i = 0; $i < $count; $i++) {
            $made[] = $this->createCategory(['slug' => 'cat-'.$i]);
        }

        return $made;
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
