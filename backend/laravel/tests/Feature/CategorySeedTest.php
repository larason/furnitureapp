<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategorySeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_idempotent_and_deterministic(): void
    {
        $this->seed(CategorySeeder::class);
        $firstCategories = $this->canonicalCategories();
        $firstRecommendations = $this->canonicalRecommendations();

        $this->seed(CategorySeeder::class);
        $secondCategories = $this->canonicalCategories();
        $secondRecommendations = $this->canonicalRecommendations();

        $this->assertSame(76, Category::count());
        $this->assertSame(76, Category::pluck('slug')->unique()->count());
        $this->assertSame(20, DB::table('category_recommendations')->count());

        $this->assertSame($firstCategories, $secondCategories, 'Categories canonical state must be identical after repeated seeds');
        $this->assertSame($firstRecommendations, $secondRecommendations, 'Recommendation priorities and targets must be identical after repeated seeds');
    }

    public function test_recommendation_seeding_batches_category_lookups(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (preg_match('/from [`"]categories[`"].*where [`"]slug[`"] in/i', $query->sql) === 1) {
                $queries[] = $query->sql;
            }
        });

        $this->seed(CategorySeeder::class);

        $this->assertCount(1, $queries);
    }

    private function canonicalCategories(): array
    {
        return DB::table('categories')
            ->orderBy('slug')
            ->get(['slug', 'name', 'parent_id', 'space_type', 'display_order'])
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'name' => $row->name,
                'parent_slug' => $row->parent_id ? DB::table('categories')->where('id', $row->parent_id)->value('slug') : null,
                'space_type' => $row->space_type,
                'display_order' => (int) $row->display_order,
            ])
            ->all();
    }

    private function canonicalRecommendations(): array
    {
        return DB::table('category_recommendations')
            ->join('categories as source', 'source.id', '=', 'category_recommendations.category_id')
            ->join('categories as target', 'target.id', '=', 'category_recommendations.recommended_category_id')
            ->orderBy('source.slug')
            ->orderBy('target.slug')
            ->orderBy('category_recommendations.relation_type')
            ->orderBy('category_recommendations.priority')
            ->get([
                'source.slug as source_slug',
                'target.slug as target_slug',
                'category_recommendations.relation_type',
                'category_recommendations.priority',
            ])
            ->map(fn ($row) => [$row->source_slug, $row->target_slug, $row->relation_type, (int) $row->priority])
            ->all();
    }

    public function test_single_root_container_exists_with_null_parent(): void
    {
        $this->seed(CategorySeeder::class);

        $root = Category::whereNull('parent_id')->first();

        $this->assertNotNull($root);
        $this->assertSame('furnitures-root', $root->slug);
        $this->assertSame('Furnitures Root', $root->name);
        $this->assertSame(1, Category::whereNull('parent_id')->count());
    }

    public function test_taxonomy_levels_have_expected_counts(): void
    {
        $this->seed(CategorySeeder::class);

        $root = Category::whereNull('parent_id')->with('children.children.children')->first();

        $rooms = $root->children;
        $this->assertCount(6, $rooms);

        $groupings = $rooms->flatMap(fn ($room) => $room->children);
        $this->assertCount(16, $groupings);

        $types = $groupings->flatMap(fn ($grouping) => $grouping->children);
        $this->assertCount(53, $types);
    }

    public function test_seeded_categories_leave_public_fields_null(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(0, Category::query()
            ->whereNotNull('description')
            ->orWhereNotNull('image_url')
            ->count());
    }

    public function test_each_child_points_to_intended_parent(): void
    {
        $this->seed(CategorySeeder::class);

        $livingRoomId = Category::where('slug', 'living-room')->value('id');
        $this->assertNotNull($livingRoomId);
        $this->assertSame(
            $livingRoomId,
            Category::where('slug', 'seating')->value('parent_id')
        );

        $seatingId = Category::where('slug', 'seating')->value('id');
        $this->assertNotNull($seatingId);
        $this->assertSame(
            $seatingId,
            Category::where('slug', 'sofas')->value('parent_id')
        );

        $officeSeatingId = Category::where('slug', 'office-seating')->value('id');
        $this->assertNotNull($officeSeatingId);
        $this->assertSame(
            $officeSeatingId,
            Category::where('slug', 'ergonomic-task-chairs')->value('parent_id')
        );
    }

    public function test_space_types_follow_the_documented_assignment_rule(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame('hybrid', DB::table('categories')->where('slug', 'furnitures-root')->value('space_type'));

        $documentedRooms = [
            'living-room' => 'home',
            'bedroom' => 'home',
            'dining-room-kitchen' => 'home',
            'home-office-corporate-workspaces' => 'office',
            'outdoor-patio' => 'home',
            'entryway-accent' => 'home',
        ];

        $root = Category::whereNull('parent_id')->with('children.children.children')->first();
        $visited = 0;

        foreach ($root->children as $room) {
            $visited++;
            $this->assertSame(
                $documentedRooms[$room->slug],
                $room->space_type->value,
                "Room [{$room->slug}] must use its documented space_type."
            );

            foreach ($room->children as $grouping) {
                $visited++;
                $this->assertSame(
                    $room->space_type->value,
                    $grouping->space_type->value,
                    "Grouping [{$grouping->slug}] must inherit its room's space_type."
                );

                foreach ($grouping->children as $type) {
                    $visited++;
                    $this->assertSame(
                        $room->space_type->value,
                        $type->space_type->value,
                        "Type [{$type->slug}] must inherit its room's space_type."
                    );
                }
            }
        }

        $this->assertSame(
            Category::count(),
            $visited + 1,
            'The documented mapping rule must cover every seeded category.'
        );
    }

    public function test_recommendation_rows_reference_existing_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $rows = DB::table('category_recommendations')->get();
        $this->assertCount(20, $rows);

        foreach ($rows as $row) {
            $this->assertTrue(Category::where('id', $row->category_id)->exists(), "category_id {$row->category_id} must reference an existing category");
            $this->assertTrue(Category::where('id', $row->recommended_category_id)->exists(), "recommended_category_id {$row->recommended_category_id} must reference an existing category");
        }
    }

    public function test_recommendations_priority_order_is_deterministic(): void
    {
        $this->seed(CategorySeeder::class);

        $sofas = Category::where('slug', 'sofas')->first();

        $this->assertSame(
            ['coffee-tables', 'tv-stands-showcases', 'end-side-tables'],
            $sofas->recommendedCategories->pluck('slug')->all()
        );
    }

    public function test_recommendation_seed_rows_match_the_documented_canonical_table(): void
    {
        $this->seed(CategorySeeder::class);

        $expected = [
            ['beds', 'bedroom-benches', 'COMPLETE_THE_LOOK', 2],
            ['beds', 'dressers', 'COMPLETE_THE_LOOK', 4],
            ['beds', 'nightstands', 'COMPLETE_THE_LOOK', 5],
            ['beds', 'wardrobes', 'COMPLETE_THE_LOOK', 3],
            ['dining-tables', 'bar-carts', 'COMPLEMENTARY', 2],
            ['dining-tables', 'dining-chairs', 'PAIR_WITH', 5],
            ['dining-tables', 'sideboards-buffets', 'COMPLEMENTARY', 3],
            ['executive-desks', 'ergonomic-task-chairs', 'PAIR_WITH', 5],
            ['executive-desks', 'filing-cabinets', 'COMPLEMENTARY', 3],
            ['sectionals', 'coffee-tables', 'COMPLETE_THE_LOOK', 5],
            ['sectionals', 'end-side-tables', 'COMPLETE_THE_LOOK', 3],
            ['sectionals', 'tv-stands-showcases', 'COMPLETE_THE_LOOK', 4],
            ['sofas', 'coffee-tables', 'COMPLETE_THE_LOOK', 5],
            ['sofas', 'end-side-tables', 'COMPLETE_THE_LOOK', 3],
            ['sofas', 'tv-stands-showcases', 'COMPLETE_THE_LOOK', 4],
            ['standing-adjustable-desks', 'ergonomic-task-chairs', 'PAIR_WITH', 5],
            ['standing-adjustable-desks', 'filing-cabinets', 'COMPLEMENTARY', 3],
            ['tv-stands-showcases', 'bookcases', 'COMPLEMENTARY', 2],
            ['tv-stands-showcases', 'sofas', 'COMPLEMENTARY', 4],
            ['vanity-tables', 'dressers', 'COMPLEMENTARY', 2],
        ];

        $actual = DB::table('category_recommendations')
            ->join('categories as source', 'source.id', '=', 'category_recommendations.category_id')
            ->join('categories as target', 'target.id', '=', 'category_recommendations.recommended_category_id')
            ->orderBy('source.slug')
            ->orderBy('target.slug')
            ->get([
                'source.slug as source_slug',
                'target.slug as target_slug',
                'category_recommendations.relation_type',
                'category_recommendations.priority',
            ])
            ->map(fn ($row) => [$row->source_slug, $row->target_slug, $row->relation_type, (int) $row->priority])
            ->all();

        $this->assertSame($expected, $actual);
    }

    public function test_deferred_recommendation_targets_are_not_invented_as_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $deferred = ['Accent Rugs', 'Accent Mirrors', 'Bedroom Stools', 'Media Cabinets', 'Desk Organizers'];

        foreach ($deferred as $name) {
            $this->assertFalse(
                Category::where('name', $name)->exists(),
                "Deferred category '{$name}' must not be invented to satisfy recommendations"
            );
        }
    }
}
