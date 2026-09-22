<?php

namespace App\Models;

use App\Support\AssemblyRequired;
use App\Support\ProductType;
use Database\Factories\ProductFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'name',
    'slug',
    'sku_prefix',
    'short_description',
    'description',
    'brand',
    'room_type',
    'assembly_required',
    'primary_material',
    'is_active',
    'is_featured',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (trim($product->name ?? '') === '') {
                throw new DomainException('A product name is required.');
            }

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $product->slug ?? '') !== 1) {
                throw new DomainException('A product slug must use kebab-case.');
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('display_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)
            ->where('is_default', true);
    }

    public function furnitureRequests(): HasMany
    {
        return $this->hasMany(FurnitureRequest::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('products.is_active', true)
            ->where('products.is_published', true)
            ->whereNull('products.deleted_at')
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));
    }

    protected function casts(): array
    {
        return [
            'assembly_required' => AssemblyRequired::class,
            'is_active' => 'boolean',
            'is_published' => 'boolean',
            'product_type' => ProductType::class,
            'is_featured' => 'boolean',
        ];
    }
}
