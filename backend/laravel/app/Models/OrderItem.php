<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $sku
 * @property string $name
 * @property string|null $variant_name
 * @property int $unit_price_amount
 * @property int $quantity
 * @property int $line_total_amount
 */
#[Fillable([
    'order_id',
    'product_id',
    'variant_id',
    'sku',
    'name',
    'variant_name',
    'unit_price_amount',
    'quantity',
    'line_total_amount',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (OrderItem $item) => $item->assertValid());
    }

    public function assertValid(): void
    {
        $this->assertQuantities();
        $this->assertLineTotal();
        $this->assertVariantBelongsToProduct();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
            'unit_price_amount' => 'integer',
            'quantity' => 'integer',
            'line_total_amount' => 'integer',
        ];
    }

    private function assertQuantities(): void
    {
        if ($this->quantity < 1) {
            throw new DomainException('Order item quantity must be greater than zero.');
        }

        if ($this->unit_price_amount < 0 || $this->line_total_amount < 0) {
            throw new DomainException('Order item monetary amounts must not be negative.');
        }
    }

    private function assertLineTotal(): void
    {
        if ($this->line_total_amount !== $this->unit_price_amount * $this->quantity) {
            throw new DomainException('Order item line total must equal unit price multiplied by quantity.');
        }
    }

    private function assertVariantBelongsToProduct(): void
    {
        if ($this->variant_id === null) {
            return;
        }

        if ($this->product_id === null) {
            throw new DomainException('An order item with a variant must also reference its product.');
        }

        /** @var ProductVariant|null $variant */
        $variant = ProductVariant::query()->find($this->variant_id);

        if ($variant !== null && $variant->product_id !== $this->product_id) {
            throw new DomainException('Order item variant must belong to the same product.');
        }
    }
}
