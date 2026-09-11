<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Support\PaymentWebhookProcessingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhookEvent>
 */
class PaymentWebhookEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => null,
            'provider' => 'internal',
            'provider_event_id' => 'EVT-'.fake()->unique()->numerify('########'),
            'provider_correlation_id' => null,
            'event_type' => 'payment.succeeded',
            'processing_status' => PaymentWebhookProcessingStatus::RECEIVED,
            'received_at' => now(),
            'processed_at' => null,
            'failure_reason' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'processing_status' => PaymentWebhookProcessingStatus::RECEIVED,
            'processed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'processing_status' => PaymentWebhookProcessingStatus::PROCESSED,
            'processed_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function withCorrelation(?string $correlationId): static
    {
        return $this->state(fn () => [
            'provider_correlation_id' => $correlationId,
        ]);
    }

    public function failed(string $reason = 'Processing failed.'): static
    {
        return $this->state(fn () => [
            'processing_status' => PaymentWebhookProcessingStatus::FAILED,
            'processed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state(fn () => ['payment_id' => $payment->id]);
    }
}
