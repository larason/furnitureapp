<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $quantity
 * @property-read Cart $cart
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'cart_id',
    'product_id',
    'variant_id',
    'quantity',
])]
class CartItem extends Model
{
    public const MAX_QUANTITY = 100;

    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (CartItem $item) => $item->assertValid());
    }

    public function assertValid(): void
    {
        $this->assertQuantityInRange();
        $this->assertVariantBelongsToProduct();
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    private function assertQuantityInRange(): void
    {
        if ($this->quantity < 1 || $this->quantity > self::MAX_QUANTITY) {
            throw new DomainException('Cart item quantity must be between 1 and '.self::MAX_QUANTITY.'.');
        }
    }

    private function assertVariantBelongsToProduct(): void
    {
        if ($this->variant_id === null) {
            return;
        }

        /** @var ProductVariant|null $variant */
        $variant = ProductVariant::query()->find($this->variant_id);

        if ($variant !== null && $variant->product_id !== $this->product_id) {
            throw new DomainException('Cart item variant must belong to the same product.');
        }
    }
}
