<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reservation-allocation bookkeeping: which ProductStock row supplied which
 * units of an OrderItem. Server-created; never exposed to customers.
 *
 * @property int $id
 * @property int $order_item_id
 * @property int $product_stock_id
 * @property int $quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'order_item_id',
    'product_stock_id',
    'quantity',
])]
class OrderItemInventoryAllocation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (OrderItemInventoryAllocation $allocation): void {
            if ($allocation->quantity < 1) {
                throw new DomainException('An inventory allocation quantity must be greater than zero.');
            }
        });
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productStock(): BelongsTo
    {
        return $this->belongsTo(ProductStock::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
