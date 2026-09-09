<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\CategoryRecommendationType;
use App\Support\SpaceType;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::taxonomy() as $node) {
            $this->seedNode($node, null, SpaceType::HOME->value);
        }

        $this->seedRecommendations();
    }

    private function seedNode(array $node, ?Category $parent, string $spaceType): Category
    {
        $nodeSpaceType = $node['space_type'] ?? null;
        $resolvedSpaceType = is_string($nodeSpaceType) ? $nodeSpaceType : $spaceType;

        $category = Category::updateOrCreate(
            ['slug' => $node['slug']],
            [
                'name' => $node['name'],
                'space_type' => $resolvedSpaceType,
                'display_order' => $node['display_order'],
            ]
        );

        $category->parent_id = $parent?->id;
        $category->save();

        foreach ($node['children'] ?? [] as $child) {
            $this->seedNode($child, $category, $resolvedSpaceType);
        }

        return $category;
    }

    private function seedRecommendations(): void
    {
        foreach (self::recommendations() as $mapping) {
            $source = Category::where('slug', $mapping['source'])->first();
            $target = Category::where('slug', $mapping['target'])->first();

            if (! $source || ! $target) {
                continue;
            }

            $source->recommendedCategories()->syncWithoutDetaching([
                $target->id => [
                    'relation_type' => $mapping['relation_type'],
                    'priority' => $mapping['priority'],
                ],
            ]);
        }
    }

    private static function taxonomy(): array
    {
        return [
            [
                'name' => 'Furnitures Root',
                'slug' => 'furnitures-root',
                'display_order' => 1,
                'space_type' => SpaceType::HYBRID->value,
                'children' => [
                    self::livingRoomNode(),
                    self::bedroomNode(),
                    self::diningRoomKitchenNode(),
                    self::homeOfficeNode(),
                    self::outdoorPatioNode(),
                    self::entrywayAccentNode(),
                ],
            ],
        ];
    }

    private static function livingRoomNode(): array
    {
        return [
            'name' => 'Living Room',
            'slug' => 'living-room',
            'display_order' => 1,
            'space_type' => SpaceType::HOME->value,
            'children' => [
                [
                    'name' => 'Seating',
                    'slug' => 'seating',
                    'display_order' => 1,
                    'children' => [
                        ['name' => 'Sofas', 'slug' => 'sofas', 'display_order' => 1],
                        ['name' => 'Sectionals', 'slug' => 'sectionals', 'display_order' => 2],
                        ['name' => 'Armchairs', 'slug' => 'armchairs', 'display_order' => 3],
                        ['name' => 'Recliners', 'slug' => 'recliners', 'display_order' => 4],
                        ['name' => 'Loveseats', 'slug' => 'loveseats', 'display_order' => 5],
                        ['name' => 'Stools/Poufs', 'slug' => 'stools-poufs', 'display_order' => 6],
                    ],
                ],
                [
                    'name' => 'Tables',
                    'slug' => 'tables',
                    'display_order' => 2,
                    'children' => [
                        ['name' => 'Coffee Tables', 'slug' => 'coffee-tables', 'display_order' => 1],
                        ['name' => 'End/Side Tables', 'slug' => 'end-side-tables', 'display_order' => 2],
                        ['name' => 'Console Tables', 'slug' => 'console-tables', 'display_order' => 3],
                        ['name' => 'Nesting Tables', 'slug' => 'nesting-tables', 'display_order' => 4],
                    ],
                ],
                [
                    'name' => 'Storage & Media',
                    'slug' => 'storage-media',
                    'display_order' => 3,
                    'children' => [
                        ['name' => 'TV Stands/Showcases', 'slug' => 'tv-stands-showcases', 'display_order' => 1],
                        ['name' => 'Bookcases', 'slug' => 'bookcases', 'display_order' => 2],
                        ['name' => 'Display Cabinets', 'slug' => 'display-cabinets', 'display_order' => 3],
                    ],
                ],
            ],
        ];
    }

    private static function bedroomNode(): array
    {
        return [
            'name' => 'Bedroom',
            'slug' => 'bedroom',
            'display_order' => 2,
            'space_type' => SpaceType::HOME->value,
            'children' => [
                [
                    'name' => 'Beds',
                    'slug' => 'beds',
                    'display_order' => 1,
                    'children' => [
                        ['name' => 'Platform Beds', 'slug' => 'platform-beds', 'display_order' => 1],
                        ['name' => 'Canopy Beds', 'slug' => 'canopy-beds', 'display_order' => 2],
                        ['name' => 'Storage Beds', 'slug' => 'storage-beds', 'display_order' => 3],
                        ['name' => 'Daybeds', 'slug' => 'daybeds', 'display_order' => 4],
                        ['name' => 'Bunk Beds', 'slug' => 'bunk-beds', 'display_order' => 5],
                    ],
                ],
                [
                    'name' => 'Storage',
                    'slug' => 'storage',
                    'display_order' => 2,
                    'children' => [
                        ['name' => 'Dressers', 'slug' => 'dressers', 'display_order' => 1],
                        ['name' => 'Nightstands', 'slug' => 'nightstands', 'display_order' => 2],
                        ['name' => 'Wardrobes', 'slug' => 'wardrobes', 'display_order' => 3],
                        ['name' => 'Chest of Drawers', 'slug' => 'chest-of-drawers', 'display_order' => 4],
                    ],
                ],
                [
                    'name' => 'Vanity & Seating',
                    'slug' => 'vanity-seating',
                    'display_order' => 3,
                    'children' => [
                        ['name' => 'Vanity Tables', 'slug' => 'vanity-tables', 'display_order' => 1],
                        ['name' => 'Bedroom Benches', 'slug' => 'bedroom-benches', 'display_order' => 2],
                    ],
                ],
            ],
        ];
    }

    private static function diningRoomKitchenNode(): array
    {
        return [
            'name' => 'Dining Room & Kitchen',
            'slug' => 'dining-room-kitchen',
            'display_order' => 3,
            'space_type' => SpaceType::HOME->value,
            'children' => [
                [
                    'name' => 'Dining Sets & Tables',
                    'slug' => 'dining-sets-tables',
                    'display_order' => 1,
                    'children' => [
                        ['name' => 'Dining Tables', 'slug' => 'dining-tables', 'display_order' => 1],
                        ['name' => 'Kitchen Islands', 'slug' => 'kitchen-islands', 'display_order' => 2],
                    ],
                ],
                [
                    'name' => 'Dining Seating',
                    'slug' => 'dining-seating',
                    'display_order' => 2,
                    'children' => [
                        ['name' => 'Dining Chairs', 'slug' => 'dining-chairs', 'display_order' => 1],
                        ['name' => 'Bar & Counter Stools', 'slug' => 'bar-counter-stools', 'display_order' => 2],
                        ['name' => 'Dining Benches', 'slug' => 'dining-benches', 'display_order' => 3],
                    ],
                ],
                [
                    'name' => 'Dining Storage',
                    'slug' => 'dining-storage',
                    'display_order' => 3,
                    'children' => [
                        ['name' => 'Sideboards/Buffets', 'slug' => 'sideboards-buffets', 'display_order' => 1],
                        ['name' => 'Bar Carts', 'slug' => 'bar-carts', 'display_order' => 2],
                        ['name' => 'China Cabinets', 'slug' => 'china-cabinets', 'display_order' => 3],
                    ],
                ],
            ],
        ];
    }

    private static function homeOfficeNode(): array
    {
        return [
            'name' => 'Home Office & Corporate Workspaces',
            'slug' => 'home-office-corporate-workspaces',
            'display_order' => 4,
            'space_type' => SpaceType::OFFICE->value,
            'children' => [
                [
                    'name' => 'Desks',
                    'slug' => 'desks',
                    'display_order' => 1,
                    'children' => [
                        ['name' => 'Executive Desks', 'slug' => 'executive-desks', 'display_order' => 1],
                        ['name' => 'Standing/Adjustable Desks', 'slug' => 'standing-adjustable-desks', 'display_order' => 2],
                        ['name' => 'Corner/L-Shaped Desks', 'slug' => 'corner-l-shaped-desks', 'display_order' => 3],
                        ['name' => 'Writing Desks', 'slug' => 'writing-desks', 'display_order' => 4],
                    ],
                ],
                [
                    'name' => 'Office Seating',
                    'slug' => 'office-seating',
                    'display_order' => 2,
                    'children' => [
                        ['name' => 'Ergonomic Task Chairs', 'slug' => 'ergonomic-task-chairs', 'display_order' => 1],
                        ['name' => 'Executive Chairs', 'slug' => 'executive-chairs', 'display_order' => 2],
                        ['name' => 'Visitor Chairs', 'slug' => 'visitor-chairs', 'display_order' => 3],
                    ],
                ],
                [
                    'name' => 'Office Storage',
                    'slug' => 'office-storage',
                    'display_order' => 3,
                    'children' => [
                        ['name' => 'Filing Cabinets', 'slug' => 'filing-cabinets', 'display_order' => 1],
                        ['name' => 'Credenzas', 'slug' => 'credenzas', 'display_order' => 2],
                        ['name' => 'Office Bookcases', 'slug' => 'office-bookcases', 'display_order' => 3],
                    ],
                ],
            ],
        ];
    }

    private static function outdoorPatioNode(): array
    {
        return [
            'name' => 'Outdoor & Patio',
            'slug' => 'outdoor-patio',
            'display_order' => 5,
            'space_type' => SpaceType::HOME->value,
            'children' => [
                [
                    'name' => 'Outdoor Seating',
                    'slug' => 'outdoor-seating',
                    'display_order' => 1,
                    'children' => [
                        ['name' => 'Patio Sofas', 'slug' => 'patio-sofas', 'display_order' => 1],
                        ['name' => 'Loungers', 'slug' => 'loungers', 'display_order' => 2],
                        ['name' => 'Hammocks', 'slug' => 'hammocks', 'display_order' => 3],
                    ],
                ],
                [
                    'name' => 'Outdoor Dining',
                    'slug' => 'outdoor-dining',
                    'display_order' => 2,
                    'children' => [
                        ['name' => 'Patio Tables', 'slug' => 'patio-tables', 'display_order' => 1],
                        ['name' => 'Outdoor Bar Sets', 'slug' => 'outdoor-bar-sets', 'display_order' => 2],
                    ],
                ],
            ],
        ];
    }

    private static function entrywayAccentNode(): array
    {
        return [
            'name' => 'Entryway & Accent',
            'slug' => 'entryway-accent',
            'display_order' => 6,
            'space_type' => SpaceType::HOME->value,
            'children' => [
                [
                    'name' => 'Entryway Furniture',
                    'slug' => 'entryway-furniture',
                    'display_order' => 1,
                    'children' => [
                        ['name' => 'Shoe Cabinets', 'slug' => 'shoe-cabinets', 'display_order' => 1],
                        ['name' => 'Coat Racks', 'slug' => 'coat-racks', 'display_order' => 2],
                        ['name' => 'Entryway Benches', 'slug' => 'entryway-benches', 'display_order' => 3],
                    ],
                ],
                [
                    'name' => 'Accent Pieces',
                    'slug' => 'accent-pieces',
                    'display_order' => 2,
                    'children' => [
                        ['name' => 'Accent Tables', 'slug' => 'accent-tables', 'display_order' => 1],
                        ['name' => 'Accent Chairs', 'slug' => 'accent-chairs', 'display_order' => 2],
                        ['name' => 'Room Dividers', 'slug' => 'room-dividers', 'display_order' => 3],
                    ],
                ],
            ],
        ];
    }

    private static function recommendations(): array
    {
        $complementary = CategoryRecommendationType::COMPLEMENTARY->value;
        $pairWith = CategoryRecommendationType::PAIR_WITH->value;
        $completeTheLook = CategoryRecommendationType::COMPLETE_THE_LOOK->value;

        return [
            ['source' => 'sofas', 'target' => 'coffee-tables', 'relation_type' => $completeTheLook, 'priority' => 5],
            ['source' => 'sofas', 'target' => 'tv-stands-showcases', 'relation_type' => $completeTheLook, 'priority' => 4],
            ['source' => 'sofas', 'target' => 'end-side-tables', 'relation_type' => $completeTheLook, 'priority' => 3],
            ['source' => 'sectionals', 'target' => 'coffee-tables', 'relation_type' => $completeTheLook, 'priority' => 5],
            ['source' => 'sectionals', 'target' => 'tv-stands-showcases', 'relation_type' => $completeTheLook, 'priority' => 4],
            ['source' => 'sectionals', 'target' => 'end-side-tables', 'relation_type' => $completeTheLook, 'priority' => 3],

            ['source' => 'beds', 'target' => 'nightstands', 'relation_type' => $completeTheLook, 'priority' => 5],
            ['source' => 'beds', 'target' => 'dressers', 'relation_type' => $completeTheLook, 'priority' => 4],
            ['source' => 'beds', 'target' => 'wardrobes', 'relation_type' => $completeTheLook, 'priority' => 3],
            ['source' => 'beds', 'target' => 'bedroom-benches', 'relation_type' => $completeTheLook, 'priority' => 2],

            ['source' => 'dining-tables', 'target' => 'dining-chairs', 'relation_type' => $pairWith, 'priority' => 5],
            ['source' => 'dining-tables', 'target' => 'sideboards-buffets', 'relation_type' => $complementary, 'priority' => 3],
            ['source' => 'dining-tables', 'target' => 'bar-carts', 'relation_type' => $complementary, 'priority' => 2],

            ['source' => 'executive-desks', 'target' => 'ergonomic-task-chairs', 'relation_type' => $pairWith, 'priority' => 5],
            ['source' => 'executive-desks', 'target' => 'filing-cabinets', 'relation_type' => $complementary, 'priority' => 3],
            ['source' => 'standing-adjustable-desks', 'target' => 'ergonomic-task-chairs', 'relation_type' => $pairWith, 'priority' => 5],
            ['source' => 'standing-adjustable-desks', 'target' => 'filing-cabinets', 'relation_type' => $complementary, 'priority' => 3],

            ['source' => 'tv-stands-showcases', 'target' => 'sofas', 'relation_type' => $complementary, 'priority' => 4],
            ['source' => 'tv-stands-showcases', 'target' => 'bookcases', 'relation_type' => $complementary, 'priority' => 2],

            ['source' => 'vanity-tables', 'target' => 'dressers', 'relation_type' => $complementary, 'priority' => 2],
        ];
    }
}
