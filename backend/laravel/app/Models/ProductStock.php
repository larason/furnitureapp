<?php

namespace App\Models;

use Database\Factories\ProductStockFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property string $warehouse_location
 * @property int $quantity
 * @property int $reserved_quantity
 * @property int $availableQuantity
 */
#[Fillable([
    'product_variant_id',
    'warehouse_location',
    'quantity',
    'reserved_quantity',
])]
class ProductStock extends Model
{
    public const DEFAULT_LOCATION = 'main';

    /** @use HasFactory<ProductStockFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (ProductStock $stock) => $stock->assertValid());
    }

    public function assertValid(): void
    {
        if ($this->reserved_quantity > $this->quantity) {
            throw new DomainException('Reserved quantity cannot exceed quantity.');
        }
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    protected function availableQuantity(): Attribute
    {
        return Attribute::get(fn (): int => $this->quantity - $this->reserved_quantity);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }
}
