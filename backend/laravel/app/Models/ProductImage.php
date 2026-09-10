<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property string $file_path
 * @property string|null $alt_text
 * @property int $sort_order
 * @property bool $is_primary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'product_variant_id',
    'file_path',
    'alt_text',
    'sort_order',
    'is_primary',
])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    public const DEFAULT_SORT_ORDER = 0;

    public const DEFAULT_IS_PRIMARY = false;

    protected static function booted(): void
    {
        static::saving(fn (ProductImage $image) => $image->assertValid());
    }

    public function assertValid(): void
    {
        $this->assertProductVariantConsistency();
        $this->assertSinglePrimary();
    }

    private function assertProductVariantConsistency(): void
    {
        if ($this->product_variant_id === null) {
            return;
        }

        /** @var ProductVariant|null $variant */
        $variant = ProductVariant::query()->find($this->product_variant_id);

        if ($variant !== null && $variant->product_id !== $this->product_id) {
            throw new DomainException('Product image variant must belong to the same product.');
        }
    }

    private function assertSinglePrimary(): void
    {
        if (! $this->is_primary) {
            return;
        }

        $query = ProductImage::query()
            ->where('product_id', $this->product_id)
            ->where('is_primary', true);

        if ($this->exists) {
            $query->whereKeyNot($this->id);
        }

        if ($query->exists()) {
            throw new DomainException('A product can only have one primary image.');
        }
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }
}
