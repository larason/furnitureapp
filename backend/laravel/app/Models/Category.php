<?php

namespace App\Models;

use App\Support\SpaceType;
use Database\Factories\CategoryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'image_url', 'slug', 'space_type', 'display_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category->slug ?? '') !== 1) {
                throw new DomainException('A category slug must use kebab-case.');
            }

            if ($category->parent_id !== null && $category->parent_id === $category->id) {
                throw new DomainException('A category cannot reference itself as its own parent.');
            }

            if (($category->display_order ?? 0) < 0) {
                throw new DomainException('A category display order must not be negative.');
            }
        });
    }

    public function changeParent(?int $parentId): void
    {
        if ($parentId !== null && $parentId === $this->id) {
            throw new DomainException('A category cannot reference itself as its own parent.');
        }

        $this->getConnection()->transaction(function () use ($parentId): void {
            $category = $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($parentId !== null) {
                $this->assertAcyclicUnderLock($category, $parentId);
            }

            $category->parent_id = $parentId;
            $category->save();
        });
    }

    private function assertAcyclicUnderLock(Category $category, int $parentId): void
    {
        $ids = [$category->id];

        do {
            $current = $parentId;
            while ($current !== null && ! in_array($current, $ids, true)) {
                $ids[] = $current;
                $current = $category->newQuery()->whereKey($current)->value('parent_id');
            }

            $locked = $this->lockChain($category, $ids);

            $current = $parentId;
            while ($current !== null && isset($locked[$current])) {
                if ($current === $category->id) {
                    throw new DomainException('A category parent assignment cannot create a hierarchy cycle.');
                }
                $current = $locked[$current]->parent_id;
            }
        } while ($current !== null && $category->newQuery()->whereKey($current)->exists());
    }

    /** @return Collection<int, Category> */
    private function lockChain(Category $category, array $ids): Collection
    {
        return $category->newQuery()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('display_order');
    }

    public function recommendedCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_recommendations',
            'category_id',
            'recommended_category_id'
        )
            ->withPivot('relation_type', 'priority')
            ->withTimestamps()
            ->orderByPivot('priority', 'desc');
    }

    public function recommendedByCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_recommendations',
            'recommended_category_id',
            'category_id'
        )
            ->withPivot('relation_type', 'priority')
            ->withTimestamps()
            ->orderByPivot('priority', 'desc');
    }

    protected function casts(): array
    {
        return [
            'space_type' => SpaceType::class,
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
