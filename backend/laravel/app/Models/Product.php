<?php

namespace App\Models;

use App\Support\AssemblyRequired;
use Database\Factories\ProductFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected function casts(): array
    {
        return [
            'assembly_required' => AssemblyRequired::class,
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}
