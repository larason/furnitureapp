<?php

namespace App\Models;

use App\Support\OrderActorType;
use App\Support\OrderStatus;
use Database\Factories\OrderStatusHistoryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property OrderActorType $actor_type
 * @property int|null $actor_id
 * @property string|null $customer_note
 * @property string|null $internal_note
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 */
#[Fillable([
    'order_id',
    'from_status',
    'to_status',
    'actor_type',
    'actor_id',
    'customer_note',
    'internal_note',
    'occurred_at',
])]
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    /** @use HasFactory<OrderStatusHistoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(fn (OrderStatusHistory $history) => $history->assertValid());
        static::deleting(fn () => throw new DomainException('Order status history events are append-only and cannot be deleted.'));
        static::updating(fn () => throw new DomainException('Order status history events are immutable.'));
    }

    public function assertValid(): void
    {
        $this->assertClosedStatuses();
        $this->assertActor();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'actor_type' => OrderActorType::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    private function assertClosedStatuses(): void
    {
        if ($this->from_status !== null && ! in_array($this->from_status, OrderStatus::cases(), true)) {
            throw new DomainException('from_status must be a closed V1 order status or null.');
        }

        if (! in_array($this->to_status, OrderStatus::cases(), true)) {
            throw new DomainException('to_status must be a closed V1 order status.');
        }
    }

    private function assertActor(): void
    {
        if ($this->actor_type === OrderActorType::SYSTEM && $this->actor_id !== null) {
            throw new DomainException('System events must not carry an actor id.');
        }

        if ($this->actor_type !== OrderActorType::SYSTEM && $this->actor_id === null) {
            throw new DomainException('Customer, staff, and admin events require an actor id.');
        }
    }
}
