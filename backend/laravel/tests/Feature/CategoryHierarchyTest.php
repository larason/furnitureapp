<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\SpaceType;
use Database\Seeders\CategorySeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_category_has_no_parent(): void
    {
        $root = $this->createCategory(['slug' => 'root']);

        $this->assertNull($root->parent_id);
        $this->assertNull($root->parent);
    }

    public function test_child_resolves_its_parent(): void
    {
        $parent = $this->createCategory(['slug' => 'parent']);
        $child = $this->createCategory(['slug' => 'child'], $parent);

        $this->assertTrue($child->parent->is($parent));
    }

    public function test_parent_resolves_children(): void
    {
        $parent = $this->createCategory(['slug' => 'parent']);
        $first = $this->createCategory(['slug' => 'first'], $parent);
        $second = $this->createCategory(['slug' => 'second'], $parent);

        $children = $parent->fresh()->children;

        $this->assertCount(2, $children);
        $this->assertTrue($children->contains($first));
        $this->assertTrue($children->contains($second));
    }

    public function test_children_are_ordered_by_display_order(): void
    {
        $parent = $this->createCategory(['slug' => 'parent']);
        $this->createCategory(['slug' => 'last', 'display_order' => 9], $parent);
        $this->createCategory(['slug' => 'first', 'display_order' => 1], $parent);
        $this->createCategory(['slug' => 'middle', 'display_order' => 5], $parent);

        $ordered = $parent->fresh()->children->pluck('slug')->all();

        $this->assertSame(['first', 'middle', 'last'], $ordered);
    }

    public function test_self_parenting_is_rejected(): void
    {
        $category = $this->createCategory(['slug' => 'self-ref']);

        $category->parent_id = $category->id;

        $this->expectException(DomainException::class);
        $category->save();
    }

    public function test_two_node_cycle_is_rejected(): void
    {
        $parent = $this->createCategory(['slug' => 'cycle-parent']);
        $child = $this->createCategory(['slug' => 'cycle-child'], $parent);

        $this->expectException(DomainException::class);
        $parent->changeParent($child->id);
    }

    public function test_deeper_cycle_is_rejected(): void
    {
        $a = $this->createCategory(['slug' => 'cycle-a']);
        $b = $this->createCategory(['slug' => 'cycle-b'], $a);
        $c = $this->createCategory(['slug' => 'cycle-c'], $b);

        $this->expectException(DomainException::class);
        $a->changeParent($c->id);
    }

    public function test_seeded_hierarchy_has_intended_structure(): void
    {
        $this->seed(CategorySeeder::class);

        $root = Category::where('slug', 'furnitures-root')->first();
        $this->assertNotNull($root);
        $this->assertNull($root->parent_id);

        $this->assertSame(
            ['living-room', 'bedroom', 'dining-room-kitchen', 'home-office-corporate-workspaces', 'outdoor-patio', 'entryway-accent'],
            $root->children->pluck('slug')->all()
        );

        $livingRoom = Category::where('slug', 'living-room')->first();
        $this->assertSame(['seating', 'tables', 'storage-media'], $livingRoom->children->pluck('slug')->all());

        $seating = Category::where('slug', 'seating')->first();
        $this->assertSame(
            ['sofas', 'sectionals', 'armchairs', 'recliners', 'loveseats', 'stools-poufs'],
            $seating->children->pluck('slug')->all()
        );
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
