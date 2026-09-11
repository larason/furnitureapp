<?php

namespace App\Models;

use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use Database\Factories\OrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $order_reference
 * @property OrderStatus|null $status
 * @property FulfillmentType $fulfillment_type
 * @property DeliveryFeeStatus $delivery_fee_status
 * @property string $currency
 * @property int|null $subtotal_amount
 * @property int|null $delivery_fee_amount
 * @property int|null $total_amount
 * @property string|null $recipient_name
 * @property string|null $recipient_phone
 * @property array|null $delivery_address
 */
#[Fillable([
    'fulfillment_type',
    'recipient_name',
    'recipient_phone',
    'delivery_address',
])]
class Order extends Model
{
    public const CURRENCY_TZS = 'TZS';

    public const REFERENCE_PREFIX = 'OD-';

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (Order $order) => $order->assertValid());
    }

    public function assertValid(): void
    {
        $this->assertReferenceImmutable();
        $this->assertInitialStatus();
        $this->assertFinancialImmutability();
        $this->assertRequiredAmount();
        $this->assertPickupState();
        $this->assertDeliveryState();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function isPickup(): bool
    {
        return $this->fulfillment_type === FulfillmentType::PICKUP;
    }

    public function isDelivery(): bool
    {
        return $this->fulfillment_type === FulfillmentType::DELIVERY;
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fulfillment_type' => FulfillmentType::class,
            'delivery_fee_status' => DeliveryFeeStatus::class,
            'subtotal_amount' => 'integer',
            'delivery_fee_amount' => 'integer',
            'total_amount' => 'integer',
            'delivery_address' => 'array',
        ];
    }

    private function assertReferenceImmutable(): void
    {
        if (preg_match('/^OD-[A-Z0-9]{5}$/', (string) $this->order_reference) !== 1) {
            throw new DomainException('Order reference must follow OD-*****.');
        }

        if ($this->exists && $this->getOriginal('order_reference') !== $this->order_reference) {
            throw new DomainException('Order reference is immutable.');
        }
    }

    private function assertInitialStatus(): void
    {
        if (! $this->exists && $this->status === null) {
            $this->status = OrderStatus::PENDING_PAYMENT;
        }

        if (! $this->exists && $this->status !== OrderStatus::PENDING_PAYMENT) {
            throw new DomainException('New orders must start in PENDING_PAYMENT status.');
        }
    }

    private function assertFinancialImmutability(): void
    {
        if (! $this->exists) {
            return;
        }

        $originallyFinalized = $this->getOriginal('delivery_fee_status') === DeliveryFeeStatus::FINALIZED;
        $originallyPastPending = $this->getOriginal('status') !== OrderStatus::PENDING_PAYMENT;
        $amountDirty = $this->isDirty('subtotal_amount')
            || $this->isDirty('delivery_fee_amount')
            || $this->isDirty('total_amount')
            || $this->isDirty('currency');

        if (($originallyFinalized || $originallyPastPending) && $amountDirty) {
            throw new DomainException('Order financial values are immutable once the order is past PENDING_PAYMENT or the delivery fee is finalized.');
        }
    }

    private function assertRequiredAmount(): void
    {
        if ($this->subtotal_amount === null) {
            throw new DomainException('Order subtotal is required.');
        }
    }

    private function assertPickupState(): void
    {
        if (! $this->isPickup()) {
            return;
        }

        if ($this->delivery_fee_status !== DeliveryFeeStatus::FINALIZED
            || $this->delivery_fee_amount !== 0
            || $this->total_amount !== $this->subtotal_amount
            || $this->delivery_address !== null) {
            throw new DomainException('Pickup orders require finalized zero delivery fee, total equal to subtotal, and no delivery address.');
        }
    }

    private function assertDeliveryState(): void
    {
        if (! $this->isDelivery()) {
            return;
        }

        if ($this->delivery_fee_status === DeliveryFeeStatus::PENDING) {
            $this->assertDeliveryPendingState();

            return;
        }

        if ($this->delivery_fee_amount === null
            || $this->delivery_fee_amount < 0
            || $this->total_amount !== $this->subtotal_amount + $this->delivery_fee_amount) {
            throw new DomainException('Finalized delivery orders require a non-negative delivery fee and total equal to subtotal plus delivery fee.');
        }
    }

    private function assertDeliveryPendingState(): void
    {
        if ($this->delivery_fee_amount !== null || $this->total_amount !== $this->subtotal_amount) {
            throw new DomainException('Pending delivery orders require a null delivery fee and a provisional total equal to subtotal.');
        }
    }
}
