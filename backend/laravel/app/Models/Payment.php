<?php

namespace App\Models;

use App\Support\DiagnosticText;
use App\Support\PaymentStatus;
use Database\Factories\PaymentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $payment_reference
 * @property string $provider
 * @property string $method
 * @property PaymentStatus $status
 * @property int $amount
 * @property string $currency
 * @property string|null $provider_transaction_id
 * @property string|null $provider_reference
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon|null $initiated_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'order_id',
    'payment_reference',
    'provider',
    'method',
    'status',
    'amount',
    'currency',
    'provider_transaction_id',
    'provider_reference',
    'failure_code',
    'failure_message',
    'initiated_at',
    'confirmed_at',
    'expires_at',
])]
class Payment extends Model
{
    public const TABLE = 'payments';

    public const CURRENCY_TZS = 'TZS';

    public const REFERENCE_PREFIX = 'PAY-';

    private const REFERENCE_PATTERN = '/^PAY-[A-Z0-9]{8}$/';

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $table = self::TABLE;

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $payment->failure_message = DiagnosticText::sanitize($payment->failure_message);
            $payment->assertValid();
        });
    }

    public function assertValid(): void
    {
        $this->assertReference();
        $this->assertImmutability();
        $this->assertProviderAndMethod();
        $this->assertMoney();
        $this->assertStatus();
        $this->assertTimestamps();
    }

    public function assertConsistentWithOrder(Order $order): void
    {
        $this->assertCurrencyMatchesOrder($order);
        $this->assertAmountMatchesOrder($order);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'initiated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    private function assertReference(): void
    {
        $reference = (string) $this->payment_reference;

        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new DomainException('Payment reference must follow PAY-********.');
        }

        $this->assertReferenceImmutable();
    }

    private function assertReferenceImmutable(): void
    {
        if ($this->exists && $this->getOriginal('payment_reference') !== $this->payment_reference) {
            throw new DomainException('Payment reference is immutable.');
        }
    }

    private function assertImmutability(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->assertFieldImmutable('order_id');
        $this->assertFieldImmutable('provider');
        $this->assertFieldImmutable('amount');
        $this->assertFieldImmutable('currency');
        $this->assertFieldImmutable('initiated_at');
        $this->assertProviderIdentityImmutable('provider_transaction_id');
        $this->assertProviderIdentityImmutable('provider_reference');
    }

    private function assertFieldImmutable(string $field): void
    {
        if ($this->getOriginal($field) !== $this->{$field}) {
            throw new DomainException("Payment {$field} is immutable.");
        }
    }

    private function assertProviderIdentityImmutable(string $field): void
    {
        $original = $this->getOriginal($field);

        if ($original !== null && $original !== $this->{$field}) {
            throw new DomainException("Payment {$field} is immutable once established.");
        }
    }

    private function assertProviderAndMethod(): void
    {
        $this->assertNonEmptyString('provider', $this->provider);
        $this->assertNonEmptyString('method', $this->method);
    }

    private function assertNonEmptyString(string $field, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Payment {$field} is required.");
        }
    }

    private function assertMoney(): void
    {
        if ($this->amount < 0) {
            throw new DomainException('Payment amount must not be negative.');
        }

        if ($this->currency !== self::CURRENCY_TZS) {
            throw new DomainException('Payment currency must be TZS for V1.');
        }
    }

    private function assertStatus(): void
    {
        if (! in_array($this->status, PaymentStatus::cases(), true)) {
            throw new DomainException('Payment status must be a closed V1 payment status.');
        }
    }

    private function assertTimestamps(): void
    {
        if ($this->initiated_at === null) {
            throw new DomainException('Payment initiated_at is required.');
        }
    }

    private function assertCurrencyMatchesOrder(Order $order): void
    {
        if ($this->currency !== $order->currency) {
            throw new DomainException('Payment currency must match the order currency.');
        }
    }

    private function assertAmountMatchesOrder(Order $order): void
    {
        $expected = $order->total_amount;

        if ($expected !== null && $this->amount !== $expected) {
            throw new DomainException('Payment amount must match the authoritative order total.');
        }
    }
}
