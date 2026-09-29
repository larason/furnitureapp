<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Support\PaymentWebhookProcessingStatus;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentWebhookEventSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('payment_webhook_events'));
        $this->assertTrue(Schema::hasColumns('payment_webhook_events', [
            'id',
            'payment_id',
            'provider',
            'provider_event_id',
            'provider_correlation_id',
            'event_type',
            'processing_status',
            'received_at',
            'processed_at',
            'failure_reason',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_provider_event_unique_constraint_exists(): void
    {
        PaymentWebhookEvent::factory()->create([
            'provider' => 'provider_a',
            'provider_event_id' => 'EVT-12345678',
        ]);

        $this->expectException(QueryException::class);

        PaymentWebhookEvent::factory()->create([
            'provider' => 'provider_a',
            'provider_event_id' => 'EVT-12345678',
        ]);
    }

    public function test_provider_is_immutable_after_creation(): void
    {
        $event = PaymentWebhookEvent::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Webhook event provider is immutable.');

        $event->provider = 'provider_b';
        $event->save();
    }

    public function test_provider_event_id_is_immutable_after_creation(): void
    {
        $event = PaymentWebhookEvent::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Webhook event provider_event_id is immutable.');

        $event->provider_event_id = 'EVT-CHANGED';
        $event->save();
    }

    public function test_same_event_id_allowed_for_different_providers(): void
    {
        $first = PaymentWebhookEvent::factory()->create([
            'provider' => 'provider_a',
            'provider_event_id' => 'EVT-SHARED',
        ]);

        $second = PaymentWebhookEvent::factory()->create([
            'provider' => 'provider_b',
            'provider_event_id' => 'EVT-SHARED',
        ]);

        $this->assertSame('EVT-SHARED', $first->provider_event_id);
        $this->assertSame('EVT-SHARED', $second->provider_event_id);
        $this->assertNotSame($first->provider, $second->provider);
    }

    public function test_duplicate_provider_event_rejected_at_durable_level(): void
    {
        PaymentWebhookEvent::factory()->create([
            'provider' => 'provider_a',
            'provider_event_id' => 'EVT-DUP-001',
        ]);

        try {
            PaymentWebhookEvent::factory()->create([
                'provider' => 'provider_a',
                'provider_event_id' => 'EVT-DUP-001',
            ]);
            $this->fail('Duplicate provider event should have been rejected.');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, PaymentWebhookEvent::where('provider', 'provider_a')->where('provider_event_id', 'EVT-DUP-001')->count());
    }

    public function test_event_can_exist_without_payment(): void
    {
        $event = PaymentWebhookEvent::factory()->create(['payment_id' => null]);

        $this->assertNull($event->payment_id);
        $this->assertNull($event->payment);
    }

    public function test_event_can_preserve_correlation_before_payment_match(): void
    {
        $event = PaymentWebhookEvent::factory()
            ->create(['payment_id' => null, 'provider_correlation_id' => 'CORR-abc123']);

        $this->assertNull($event->payment_id);
        $this->assertSame('CORR-abc123', $event->provider_correlation_id);
    }

    public function test_correlation_is_nullable(): void
    {
        $event = PaymentWebhookEvent::factory()->create(['provider_correlation_id' => null]);

        $this->assertNull($event->provider_correlation_id);
    }

    public function test_correlation_can_join_unmatched_event_to_payment(): void
    {
        $payment = Payment::factory()->create([
            'provider_reference' => 'CORR-join-001',
        ]);
        $event = PaymentWebhookEvent::factory()
            ->create([
                'payment_id' => null,
                'provider_correlation_id' => 'CORR-join-001',
            ]);

        $matched = PaymentWebhookEvent::query()
            ->where('payment_id', null)
            ->where('provider_correlation_id', 'CORR-join-001')
            ->first();

        $this->assertNotNull($matched);
        $this->assertTrue($event->is($matched));

        $event->payment_id = $payment->id;
        $event->save();

        $this->assertTrue($event->fresh()->payment->is($payment));
    }

    public function test_blank_correlation_is_rejected(): void
    {
        $event = PaymentWebhookEvent::factory()->make(['provider_correlation_id' => '  ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('provider_correlation_id must not be blank.');

        $event->save();
    }

    public function test_event_can_be_matched_to_payment_later(): void
    {
        $event = PaymentWebhookEvent::factory()->create(['payment_id' => null]);
        $payment = Payment::factory()->create();

        $event->payment_id = $payment->id;
        $event->save();

        $this->assertTrue($event->fresh()->payment->is($payment));
    }

    public function test_deleting_payment_nulls_payment_id_without_deleting_event(): void
    {
        $payment = Payment::factory()->create();
        $event = PaymentWebhookEvent::factory()->forPayment($payment)->create();

        $payment->delete();

        $fresh = $event->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->payment_id);
    }

    public function test_processing_status_is_closed(): void
    {
        foreach (PaymentWebhookProcessingStatus::cases() as $status) {
            $state = match ($status) {
                PaymentWebhookProcessingStatus::RECEIVED => ['processed_at' => null],
                default => ['processed_at' => now()],
            };

            $event = PaymentWebhookEvent::factory()->create([
                'processing_status' => $status,
                ...$state,
            ]);

            $this->assertSame($status, $event->fresh()->processing_status);
        }
    }

    public function test_received_status_requires_null_processed_at(): void
    {
        $event = PaymentWebhookEvent::factory()->make([
            'processing_status' => PaymentWebhookProcessingStatus::RECEIVED,
            'processed_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Webhook event RECEIVED must not have processed_at.');

        $event->save();
    }

    public function test_processed_status_requires_processed_at(): void
    {
        $event = PaymentWebhookEvent::factory()->make([
            'processing_status' => PaymentWebhookProcessingStatus::PROCESSED,
            'processed_at' => null,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Webhook event PROCESSED and FAILED require processed_at.');

        $event->save();
    }

    public function test_failed_status_requires_processed_at(): void
    {
        $event = PaymentWebhookEvent::factory()->make([
            'processing_status' => PaymentWebhookProcessingStatus::FAILED,
            'processed_at' => null,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Webhook event PROCESSED and FAILED require processed_at.');

        $event->save();
    }

    public function test_valid_state_combinations_are_persisted(): void
    {
        $received = PaymentWebhookEvent::factory()->received()->create();
        $processed = PaymentWebhookEvent::factory()->processed()->create();
        $failed = PaymentWebhookEvent::factory()->failed('boom')->create();

        $this->assertNull($received->processed_at);
        $this->assertNotNull($processed->processed_at);
        $this->assertNotNull($failed->processed_at);
    }

    public function test_invalid_processing_status_is_rejected(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('"QUEUED" is not a valid backing value for enum App\\Support\\PaymentWebhookProcessingStatus');

        $event = PaymentWebhookEvent::factory()->make(['processing_status' => 'QUEUED']);

        $event->save();
    }

    public function test_received_at_is_required(): void
    {
        $event = PaymentWebhookEvent::factory()->make(['received_at' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('received_at is required.');

        $event->save();
    }

    public function test_failure_reason_is_redacted_for_secrets(): void
    {
        $event = PaymentWebhookEvent::factory()->create([
            'failure_reason' => 'Signature failed sk_live_abcToken123 for card 5105105105105100',
        ]);

        $this->assertStringNotContainsString('sk_live_abcToken123', $event->failure_reason);
        $this->assertStringNotContainsString('5105105105105100', $event->failure_reason);
        $this->assertStringContainsString('[REDACTED]', $event->failure_reason);
    }

    public function test_failure_reason_redacts_bearer_tokens(): void
    {
        $event = PaymentWebhookEvent::factory()->create([
            'failure_reason' => 'reject: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NSJ9.sig',
        ]);

        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $event->failure_reason);
        $this->assertStringContainsString('[REDACTED]', $event->failure_reason);
    }

    public function test_failure_reason_redacts_webhook_secret(): void
    {
        $event = PaymentWebhookEvent::factory()->create([
            'failure_reason' => 'signature mismatch whsec_1a2b3c4d5e6f7g8h9i0j',
        ]);

        $this->assertStringNotContainsString('whsec_1a2b3c4d5e6f7g8h9i0j', $event->failure_reason);
        $this->assertStringContainsString('[REDACTED]', $event->failure_reason);
    }

    public function test_failure_reason_is_truncated_to_max_length(): void
    {
        $event = PaymentWebhookEvent::factory()->create([
            'failure_reason' => str_repeat('a', 1000),
        ]);

        $this->assertSame(500, mb_strlen($event->failure_reason));
    }

    public function test_blank_failure_reason_is_stored_as_null(): void
    {
        $event = PaymentWebhookEvent::factory()->create(['failure_reason' => '   ']);

        $this->assertNull($event->failure_reason);
    }

    public function test_payment_id_is_nullable_foreign_key(): void
    {
        $event = PaymentWebhookEvent::factory()->create(['payment_id' => null]);
        $this->assertNull($event->payment_id);

        $payment = Payment::factory()->create();
        $linked = PaymentWebhookEvent::factory()->forPayment($payment)->create();
        $this->assertSame($payment->id, $linked->payment_id);
    }

    public function test_no_sensitive_columns_on_webhook_events(): void
    {
        $columns = Schema::getColumnListing('payment_webhook_events');

        foreach (['webhook_secret', 'api_key', 'signature', 'access_token', 'provider_payload', 'authorization_header', 'secret'] as $col) {
            $this->assertNotContains($col, $columns, "Sensitive column {$col} must not exist");
        }
    }

    public function test_no_raw_payload_column(): void
    {
        $columns = Schema::getColumnListing('payment_webhook_events');

        $this->assertNotContains('provider_payload', $columns);
        $this->assertNotContains('payload', $columns);
        $this->assertNotContains('raw_payload', $columns);
    }

    public function test_event_belongs_to_payment(): void
    {
        $payment = Payment::factory()->create();
        $event = PaymentWebhookEvent::factory()->forPayment($payment)->create();

        $this->assertSame($payment->id, $event->payment_id);
        $this->assertTrue($event->payment->is($payment));
    }

    public function test_payment_has_many_webhook_events(): void
    {
        $payment = Payment::factory()->create();
        $first = PaymentWebhookEvent::factory()->forPayment($payment)->create();
        $second = PaymentWebhookEvent::factory()->forPayment($payment)->create();

        $events = $payment->fresh()->webhookEvents;

        $this->assertCount(2, $events);
        $this->assertTrue($events->contains($first));
        $this->assertTrue($events->contains($second));
    }
}
