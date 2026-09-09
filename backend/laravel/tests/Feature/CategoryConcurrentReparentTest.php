<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\SpaceType;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryConcurrentReparentTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_OK = 0;

    private const STATUS_CYCLE = 1;

    private const STATUS_LOCKED = 2;

    private const STATUS_ERROR = 3;

    private string $connection = 'sqlite_reparent';

    public function test_concurrent_reparenting_cannot_create_a_cycle(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension required.');
        }

        $dbFile = tempnam(sys_get_temp_dir(), 'reparent-');
        $this->assertNotFalse($dbFile);

        $this->registerConnection($dbFile);
        Artisan::call('migrate:fresh', ['--database' => $this->connection, '--force' => true]);

        $r = $this->createCategoryOn('root', null);
        $a = $this->createCategoryOn('a', $r->id);
        $b = $this->createCategoryOn('b', $r->id);

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'pcntl_fork failed');

        if ($pid === 0) {
            $code = self::STATUS_ERROR;

            try {
                $code = $this->childReparentAUnderB();
            } finally {
                exit($code);
            }
        }

        usleep(100_000);

        $parentCode = $this->runReparent($a, $b->fresh());

        pcntl_waitpid($pid, $status);
        $childCode = pcntl_wexitstatus($status);

        DB::purge($this->connection);
        $this->assertTrue($this->isKnownOutcome($parentCode), "Unexpected parent outcome: $parentCode");
        $this->assertTrue($this->isKnownOutcome($childCode), "Unexpected child outcome: $childCode");
        $this->assertFalse(
            $parentCode === self::STATUS_OK && $childCode === self::STATUS_OK,
            'Both concurrent reparents succeeded, which can only happen if writes raced.'
        );
        $this->assertNoCycles();

        @unlink($dbFile);
        @unlink($dbFile.'-wal');
        @unlink($dbFile.'-shm');
    }

    private function childReparentAUnderB(): int
    {
        DB::purge($this->connection);
        DB::connection($this->connection)->statement('PRAGMA busy_timeout=10000');

        usleep(100_000);

        return $this->runReparent($this->findCategory('b'), $this->findCategory('a'));
    }

    private function runReparent(Category $moved, Category $parent): int
    {
        try {
            $moved->changeParent($parent->id);

            return self::STATUS_OK;
        } catch (DomainException) {
            return self::STATUS_CYCLE;
        } catch (QueryException) {
            return self::STATUS_LOCKED;
        } catch (\Throwable) {
            return self::STATUS_ERROR;
        }
    }

    private function isKnownOutcome(int $code): bool
    {
        return in_array($code, [self::STATUS_OK, self::STATUS_CYCLE, self::STATUS_LOCKED], true);
    }

    private function assertNoCycles(): void
    {
        $categories = Category::on($this->connection)->get();

        foreach ($categories as $category) {
            $seen = [];
            $current = $category;

            while ($current !== null) {
                $this->assertArrayNotHasKey($current->id, $seen, 'Cycle detected in category hierarchy.');
                $seen[$current->id] = true;
                $current = $current->parent_id !== null
                    ? Category::on($this->connection)->find($current->parent_id)
                    : null;
            }
        }
    }

    private function registerConnection(string $dbFile): void
    {
        config(['database.connections.'.$this->connection => [
            'driver' => 'sqlite',
            'database' => $dbFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 10000,
            'journal_mode' => 'wal',
        ]]);
        DB::purge($this->connection);
    }

    private function createCategoryOn(string $slug, ?int $parentId): Category
    {
        $category = new Category([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'space_type' => SpaceType::HOME->value,
        ]);
        $category->setConnection($this->connection);
        $category->parent_id = $parentId;
        $category->save();

        return $category->fresh();
    }

    private function findCategory(string $slug): Category
    {
        return Category::on($this->connection)->where('slug', $slug)->firstOrFail();
    }
}
