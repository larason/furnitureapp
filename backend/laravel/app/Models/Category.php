<?php

namespace App\Models;

use App\Support\SpaceType;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'space_type', 'display_order', 'is_active'])]
class Category extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category->slug ?? '') !== 1) {
                throw new DomainException('A category slug must use kebab-case.');
            }

            if ($category->parent_id !== null && $category->parent_id === $category->id) {
                throw new DomainException('A category cannot reference itself as its own parent.');
            }

            if ($category->parent_id !== null && $category->exists) {
                $ancestorId = $category->parent_id;
                while ($ancestorId !== null) {
                    if ($ancestorId === $category->id) {
                        throw new DomainException('A category parent assignment cannot create a hierarchy cycle.');
                    }
                    $ancestorId = Category::whereKey($ancestorId)->value('parent_id');
                }
            }
        });
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
