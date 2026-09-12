<?php

namespace App\Models;

use App\Support\AddressField;
use App\Support\FulfillmentType;
use Database\Factories\DeliveryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $recipient_name
 * @property string $recipient_phone
 * @property array|null $delivery_address
 * @property string|null $delivery_instructions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'order_id',
    'recipient_name',
    'recipient_phone',
    'delivery_address',
    'delivery_instructions',
])]
class Delivery extends Model
{
    public const TABLE = 'deliveries';

    public const MAX_RECIPIENT_NAME = 255;

    public const MAX_RECIPIENT_PHONE = 50;

    public const MAX_INSTRUCTIONS = 2000;

    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    protected $table = self::TABLE;

    protected static function booted(): void
    {
        static::saving(function (Delivery $delivery): void {
            $delivery->normalizeInstructions();
            $delivery->assertValid();
        });
    }

    public function assertValid(): void
    {
        $order = $this->assertEligibleOrder();
        $this->assertRecipientSnapshot();
        $this->assertAddress();
        $this->assertInstructions();
        $this->assertOrderSnapshotImmutable();
        $this->assertSnapshotMatchesOrder($order);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'delivery_address' => 'array',
        ];
    }

    private function normalizeInstructions(): void
    {
        if ($this->delivery_instructions === null) {
            return;
        }

        $instructions = trim($this->delivery_instructions);

        if ($instructions === '') {
            $this->delivery_instructions = null;
        }
    }

    private function assertEligibleOrder(): Order
    {
        $order = Order::query()->find($this->order_id);

        if ($order === null) {
            throw new DomainException('Delivery requires an existing order.');
        }

        if ($order->fulfillment_type !== FulfillmentType::DELIVERY) {
            throw new DomainException('A delivery record is only valid for a DELIVERY order.');
        }

        return $order;
    }

    private function assertRecipientSnapshot(): void
    {
        if (trim((string) $this->recipient_name) === '') {
            throw new DomainException('Delivery recipient name is required.');
        }

        if (mb_strlen($this->recipient_name) > self::MAX_RECIPIENT_NAME) {
            throw new DomainException('Delivery recipient name is too long.');
        }

        if (trim((string) $this->recipient_phone) === '') {
            throw new DomainException('Delivery recipient phone is required.');
        }

        if (mb_strlen($this->recipient_phone) > self::MAX_RECIPIENT_PHONE) {
            throw new DomainException('Delivery recipient phone is too long.');
        }
    }

    private function assertAddress(): void
    {
        $address = $this->delivery_address;

        if (! is_array($address)) {
            throw new DomainException('Delivery address is required.');
        }

        AddressField::validate($address);
    }

    private function assertInstructions(): void
    {
        if ($this->delivery_instructions !== null && mb_strlen($this->delivery_instructions) > self::MAX_INSTRUCTIONS) {
            throw new DomainException('Delivery instructions are too long.');
        }
    }

    private function assertOrderSnapshotImmutable(): void
    {
        if (! $this->exists) {
            return;
        }

        foreach (['order_id', 'recipient_name', 'recipient_phone', 'delivery_address'] as $field) {
            if ($this->isDirty($field)) {
                throw new DomainException("Delivery {$field} is immutable once recorded.");
            }
        }
    }

    private function assertSnapshotMatchesOrder(Order $order): void
    {
        if ($this->recipient_name !== $order->recipient_name) {
            throw new DomainException('Delivery recipient name must match the order snapshot.');
        }

        if ($this->recipient_phone !== $order->recipient_phone) {
            throw new DomainException('Delivery recipient phone must match the order snapshot.');
        }

        if ($this->delivery_address !== $order->delivery_address) {
            throw new DomainException('Delivery address must match the order snapshot.');
        }
    }
}
