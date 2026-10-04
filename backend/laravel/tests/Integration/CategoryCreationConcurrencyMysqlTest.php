<?php

namespace Tests\Integration;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Services\Categories\CreateCategory;
use App\Services\Categories\CreateCategoryInput;
use App\Support\ApiErrorCode;
use App\Support\SpaceType;
use Tests\Support\RunsConcurrentWorkers;
use Tests\Support\UsesDisposableMysqlDatabase;
use Tests\TestCase;

/**
 * CAT-011 concurrency gate on real MySQL/MariaDB.
 *
 * CATEGORY_CREATION_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/CategoryCreationConcurrencyMysqlTest.php
 */
class CategoryCreationConcurrencyMysqlTest extends TestCase
{
    use RunsConcurrentWorkers;
    use UsesDisposableMysqlDatabase;

    protected function databaseConnectionName(): string
    {
        return 'mysql_category_creation';
    }

    protected function databaseEnvironmentVariable(): string
    {
        return 'CATEGORY_CREATION_MYSQL_TEST_DATABASE';
    }

    public function test_concurrent_distinct_slugs_receive_distinct_root_child_display_orders(): void
    {
        $root = Category::factory()->create([
            'name' => 'Furnitures Root',
            'slug' => CreateCategory::ROOT_SLUG,
            'parent_id' => null,
            'display_order' => 1,
        ]);

        $results = $this->runConcurrentWorkers(
            fn (): string => $this->create('concurrent-one'),
            fn (): string => $this->create('concurrent-two'),
        );

        sort($results);
        $this->assertSame(['success', 'success'], $results);

        $created = Category::query()
            ->whereIn('slug', ['concurrent-one', 'concurrent-two'])
            ->orderBy('display_order')
            ->get();
        $this->assertCount(2, $created);
        $this->assertSame([$root->id, $root->id], $created->pluck('parent_id')->all());
        $this->assertSame([1, 2], $created->pluck('display_order')->all());
        $this->assertSame([SpaceType::HYBRID, SpaceType::HYBRID], $created->pluck('space_type')->all());
    }

    public function test_concurrent_duplicate_slugs_allow_one_creation_only(): void
    {
        Category::factory()->create([
            'name' => 'Furnitures Root',
            'slug' => CreateCategory::ROOT_SLUG,
            'parent_id' => null,
            'display_order' => 1,
        ]);

        $results = $this->runConcurrentWorkers(
            fn (): string => $this->attemptCreate('concurrent-duplicate'),
            fn (): string => $this->attemptCreate('concurrent-duplicate'),
        );

        sort($results);
        $this->assertSame(['conflict', 'success'], $results);
        $this->assertSame(1, Category::query()->where('slug', 'concurrent-duplicate')->count());
    }

    private function create(string $slug): string
    {
        app(CreateCategory::class)->create(new CreateCategoryInput($slug, $slug, null, null));

        return 'success';
    }

    private function attemptCreate(string $slug): string
    {
        try {
            return $this->create($slug);
        } catch (ApiException $exception) {
            return $exception->errorCode() === ApiErrorCode::CONFLICT ? 'conflict' : 'error';
        }
    }
}
