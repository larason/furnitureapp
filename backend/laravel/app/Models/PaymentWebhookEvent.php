<?php

namespace App\Models;

use App\Support\DiagnosticText;
use App\Support\PaymentWebhookProcessingStatus;
use Database\Factories\PaymentWebhookEventFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $payment_id
 * @property string $provider
 * @property string $provider_event_id
 * @property string|null $provider_correlation_id
 * @property string $event_type
 * @property PaymentWebhookProcessingStatus $processing_status
 * @property Carbon|null $received_at
 * @property Carbon|null $processed_at
 * @property string|null $failure_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'payment_id',
    'provider',
    'provider_event_id',
    'provider_correlation_id',
    'event_type',
    'processing_status',
    'received_at',
    'processed_at',
    'failure_reason',
])]
class PaymentWebhookEvent extends Model
{
    public const TABLE = 'payment_webhook_events';

    /** @use HasFactory<PaymentWebhookEventFactory> */
    use HasFactory;

    protected $table = self::TABLE;

    protected static function booted(): void
    {
        static::saving(function (PaymentWebhookEvent $event): void {
            $event->failure_reason = DiagnosticText::sanitize($event->failure_reason);
            $event->assertValid();
        });
    }

    public function assertValid(): void
    {
        $this->assertProviderIdentity();
        $this->assertIdentityImmutable();
        $this->assertProcessingStatus();
        $this->assertReceivedAt();
        $this->assertProcessingState();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    private function assertIdentityImmutable(): void
    {
        if (! $this->exists) {
            return;
        }

        if ($this->getOriginal('provider') !== $this->provider) {
            throw new DomainException('Webhook event provider is immutable.');
        }

        if ($this->getOriginal('provider_event_id') !== $this->provider_event_id) {
            throw new DomainException('Webhook event provider_event_id is immutable.');
        }
    }

    protected function casts(): array
    {
        return [
            'processing_status' => PaymentWebhookProcessingStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    private function assertProviderIdentity(): void
    {
        $this->assertNonEmptyString('provider', $this->provider);
        $this->assertNonEmptyString('provider_event_id', $this->provider_event_id);
        $this->assertNonEmptyString('event_type', $this->event_type);
        $this->assertCorrelationSafe();
    }

    private function assertCorrelationSafe(): void
    {
        if ($this->provider_correlation_id !== null && trim($this->provider_correlation_id) === '') {
            throw new DomainException('Webhook event provider_correlation_id must not be blank.');
        }
    }

    private function assertNonEmptyString(string $field, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Webhook event {$field} is required.");
        }
    }

    private function assertProcessingStatus(): void
    {
        if (! in_array($this->processing_status, PaymentWebhookProcessingStatus::cases(), true)) {
            throw new DomainException('Webhook event processing status must be a closed internal status.');
        }
    }

    private function assertReceivedAt(): void
    {
        if ($this->received_at === null) {
            throw new DomainException('Webhook event received_at is required.');
        }
    }

    private function assertProcessingState(): void
    {
        if ($this->processing_status === PaymentWebhookProcessingStatus::RECEIVED) {
            $this->assertReceivedHasNoProcessedAt();

            return;
        }

        $this->assertTerminalHasProcessedAt();
    }

    private function assertReceivedHasNoProcessedAt(): void
    {
        if ($this->processed_at !== null) {
            throw new DomainException('Webhook event RECEIVED must not have processed_at.');
        }
    }

    private function assertTerminalHasProcessedAt(): void
    {
        if ($this->processed_at !== null) {
            return;
        }

        throw new DomainException('Webhook event PROCESSED and FAILED require processed_at.');
    }
}
