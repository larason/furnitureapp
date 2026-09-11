<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Support\PaymentStatus;
use Database\Factories\PaymentFactory;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertTrue(Schema::hasColumns('payments', [
            'id',
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
            'created_at',
            'updated_at',
        ]));
    }

    public function test_payment_belongs_to_order(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create();

        $this->assertSame($order->id, $payment->order_id);
        $this->assertTrue($payment->order->is($order));
    }

    public function test_order_has_many_payments(): void
    {
        $order = Order::factory()->create();
        $first = Payment::factory()->for($order)->create();
        $second = Payment::factory()->for($order)->create();

        $payments = $order->fresh()->payments;

        $this->assertCount(2, $payments);
        $this->assertTrue($payments->contains($first));
        $this->assertTrue($payments->contains($second));
    }

    public function test_order_can_have_multiple_payment_attempts(): void
    {
        $order = Order::factory()->create();
        $failed = Payment::factory()->for($order)->failed()->create();
        $pending = Payment::factory()->for($order)->create();

        $this->assertSame(PaymentStatus::FAILED, $failed->status);
        $this->assertSame(PaymentStatus::PENDING, $pending->status);
        $this->assertSame($order->id, $failed->order_id);
        $this->assertSame($order->id, $pending->order_id);
        $this->assertSame(2, $order->fresh()->payments()->count());
    }

    public function test_payment_reference_matches_format(): void
    {
        $payment = Payment::factory()->create();

        $this->assertMatchesRegularExpression('/^PAY-[A-Z0-9]{8}$/', $payment->payment_reference);
    }

    public function test_payment_reference_is_unique(): void
    {
        $first = Payment::factory()->create();
        $second = Payment::factory()->create();

        $this->assertNotSame($first->payment_reference, $second->payment_reference);
    }

    public function test_duplicate_payment_reference_is_rejected_by_database(): void
    {
        $existing = Payment::factory()->create();

        $this->expectException(QueryException::class);

        Payment::factory()->create(['payment_reference' => $existing->payment_reference]);
    }

    public function test_payment_reference_is_immutable_after_creation(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment reference is immutable.');

        $payment->payment_reference = PaymentFactory::generateReference();
        $payment->save();
    }

    public function test_malformed_reference_is_rejected(): void
    {
        $payment = Payment::factory()->make(['payment_reference' => 'ORDER-123']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment reference must follow PAY-');

        $payment->save();
    }

    public function test_payment_reference_not_derived_from_db_id(): void
    {
        $payment = Payment::factory()->create();

        $this->assertStringStartsWith('PAY-', $payment->payment_reference);
        $this->assertNotSame((string) $payment->id, $payment->payment_reference);
        $this->assertNotSame('PAY-'.$payment->id, $payment->payment_reference);
        $this->assertMatchesRegularExpression('/^PAY-[A-Z0-9]{8}$/', $payment->payment_reference);
    }

    public function test_amount_is_integer_minor_units(): void
    {
        $payment = Payment::factory()->create(['amount' => 35000000]);

        $this->assertSame(35000000, $payment->amount);
        $this->assertIsInt($payment->amount);
    }

    public function test_amount_cannot_be_negative(): void
    {
        $payment = Payment::factory()->make(['amount' => -100]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment amount must not be negative.');

        $payment->save();
    }

    public function test_currency_is_tzs(): void
    {
        $payment = Payment::factory()->create();

        $this->assertSame('TZS', $payment->currency);
        $this->assertSame(Payment::CURRENCY_TZS, $payment->currency);
    }

    public function test_non_tzs_currency_is_rejected(): void
    {
        $payment = Payment::factory()->make(['currency' => 'USD']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment currency must be TZS');

        $payment->save();
    }

    public function test_provider_transaction_id_is_nullable(): void
    {
        $payment = Payment::factory()->create(['provider_transaction_id' => null]);

        $this->assertNull($payment->provider_transaction_id);
    }

    public function test_provider_reference_is_nullable(): void
    {
        $payment = Payment::factory()->create(['provider_reference' => null]);

        $this->assertNull($payment->provider_reference);
    }

    public function test_failure_fields_are_nullable(): void
    {
        $payment = Payment::factory()->create();

        $this->assertNull($payment->failure_code);
        $this->assertNull($payment->failure_message);
    }

    public function test_failure_message_is_redacted_for_secrets(): void
    {
        $payment = Payment::factory()->create([
            'failure_message' => 'Card 5105 1051 0510 5100 declined by sk_live_abcToken123',
        ]);

        $this->assertStringNotContainsString('5105105105105100', $payment->failure_message);
        $this->assertStringNotContainsString('sk_live_abcToken123', $payment->failure_message);
        $this->assertStringContainsString('[REDACTED]', $payment->failure_message);
    }

    public function test_failure_message_redacts_bearer_tokens(): void
    {
        $payment = Payment::factory()->create([
            'failure_message' => 'auth failed: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NSJ9.signatureABC',
        ]);

        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $payment->failure_message);
        $this->assertStringContainsString('[REDACTED]', $payment->failure_message);
    }

    public function test_failure_message_redacts_bearer_after_authorization_label(): void
    {
        $payment = Payment::factory()->create([
            'failure_message' => 'Authorization: Bearer 1234567890abcdef',
        ]);

        $this->assertStringNotContainsString('1234567890abcdef', $payment->failure_message);
        $this->assertStringContainsString('[REDACTED]', $payment->failure_message);
    }

    public function test_failure_message_redacts_webhook_secret(): void
    {
        $payment = Payment::factory()->create([
            'failure_message' => 'webhook whsec_2k9j8h7g6f5d4s3a2q1w',
        ]);

        $this->assertStringNotContainsString('whsec_2k9j8h7g6f5d4s3a2q1w', $payment->failure_message);
        $this->assertStringContainsString('[REDACTED]', $payment->failure_message);
    }

    public function test_failure_message_is_truncated_to_max_length(): void
    {
        $payment = Payment::factory()->create([
            'failure_message' => str_repeat('a', 1000),
        ]);

        $this->assertLessThanOrEqual(500, mb_strlen($payment->failure_message));
        $this->assertSame(500, mb_strlen($payment->failure_message));
    }

    public function test_blank_failure_message_is_stored_as_null(): void
    {
        $payment = Payment::factory()->create(['failure_message' => '   ']);

        $this->assertNull($payment->failure_message);
    }

    public function test_confirmed_at_is_nullable(): void
    {
        $pending = Payment::factory()->create();

        $this->assertNull($pending->confirmed_at);

        $succeeded = Payment::factory()->succeeded()->create();

        $this->assertNotNull($succeeded->confirmed_at);
    }

    public function test_non_succeeded_payment_cannot_have_confirmed_at(): void
    {
        $payment = Payment::factory()->make([
            'status' => PaymentStatus::PENDING,
            'confirmed_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment confirmed_at must be null unless SUCCEEDED.');

        $payment->save();
    }

    public function test_succeeded_payment_requires_confirmed_at(): void
    {
        $payment = Payment::factory()->make([
            'status' => PaymentStatus::SUCCEEDED,
            'confirmed_at' => null,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment SUCCEEDED requires confirmed_at.');

        $payment->save();
    }

    public function test_confirmed_at_is_immutable_once_recorded(): void
    {
        $payment = Payment::factory()->succeeded()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment confirmed_at is immutable once recorded.');

        $payment->confirmed_at = now()->addMinute();
        $payment->save();
    }

    public function test_expires_at_is_nullable(): void
    {
        $payment = Payment::factory()->create(['expires_at' => null]);

        $this->assertNull($payment->expires_at);

        $withExpiry = Payment::factory()->create(['expires_at' => now()->addHour()]);

        $this->assertNotNull($withExpiry->expires_at);
    }

    public function test_initiated_at_is_required(): void
    {
        $payment = Payment::factory()->make(['initiated_at' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment initiated_at is required.');

        $payment->save();
    }

    public function test_core_fields_are_immutable(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(DomainException::class);

        $payment->order_id = Order::factory()->create()->id;
        $payment->save();
    }

    public function test_provider_is_immutable(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(DomainException::class);

        $payment->provider = 'other-provider';
        $payment->save();
    }

    public function test_amount_is_immutable(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(DomainException::class);

        $payment->amount += 1000;
        $payment->save();
    }

    public function test_currency_is_immutable(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment currency is immutable.');

        $payment->currency = 'USD';
        $payment->save();
    }

    public function test_payment_order_id_is_required(): void
    {
        $this->expectException(QueryException::class);

        Payment::factory()->create(['order_id' => 999999]);
    }

    public function test_deleting_order_is_restricted_when_payments_exist(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(QueryException::class);

        $payment->order->delete();
    }

    public function test_provider_transaction_id_supports_distinct_providers_with_same_id(): void
    {
        $txn = 'TXN-12345678';

        $first = Payment::factory()->create([
            'provider' => 'provider_a',
            'provider_transaction_id' => $txn,
        ]);

        $second = Payment::factory()->create([
            'provider' => 'provider_b',
            'provider_transaction_id' => $txn,
        ]);

        $this->assertSame($txn, $first->provider_transaction_id);
        $this->assertSame($txn, $second->provider_transaction_id);
        $this->assertNotSame($first->provider, $second->provider);
    }

    public function test_duplicate_provider_transaction_id_for_same_provider_is_rejected(): void
    {
        $txn = 'TXN-99999999';

        Payment::factory()->create([
            'provider' => 'provider_a',
            'provider_transaction_id' => $txn,
        ]);

        $this->expectException(QueryException::class);

        Payment::factory()->create([
            'provider' => 'provider_a',
            'provider_transaction_id' => $txn,
        ]);
    }

    public function test_payment_currency_consistency_with_order(): void
    {
        $order = Order::factory()->create(['currency' => 'TZS']);
        $payment = Payment::factory()->for($order)->make(['currency' => 'TZS', 'amount' => $order->total_amount]);

        $payment->assertConsistentWithOrder($order);
        $this->assertTrue(true);
    }

    public function test_payment_currency_mismatch_with_order_is_detected(): void
    {
        $order = Order::factory()->create(['currency' => 'TZS']);
        $payment = Payment::factory()->for($order)->make(['currency' => 'USD', 'amount' => $order->total_amount]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment currency must match the order currency.');

        $payment->assertConsistentWithOrder($order);
    }

    public function test_payment_amount_matches_order_total(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->make(['amount' => $order->total_amount]);

        $payment->assertConsistentWithOrder($order);
        $this->assertTrue(true);
    }

    public function test_payment_amount_mismatch_with_order_is_detected(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->make(['amount' => $order->total_amount + 5000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment amount must match the authoritative order total.');

        $payment->assertConsistentWithOrder($order);
    }

    public function test_facade_has_no_sensitive_payment_columns(): void
    {
        $columns = Schema::getColumnListing('payments');

        foreach (['card_number', 'card_pan', 'cvv', 'cvc', 'pin', 'provider_secret', 'webhook_secret', 'api_key', 'access_token', 'provider_payload', 'card_token'] as $col) {
            $this->assertNotContains($col, $columns, "Sensitive column {$col} must not exist on payments");
        }
    }

    public function test_money_not_float(): void
    {
        $columns = Schema::getColumnListing('payments');

        $this->assertContains('amount', $columns);

        $payment = Payment::factory()->create(['amount' => 12345]);

        $this->assertIsInt($payment->amount);
        $this->assertSame(12345, $payment->fresh()->amount);
    }

    public function test_status_is_closed_payment_status(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $confirmedAt = $status === PaymentStatus::SUCCEEDED ? now() : null;

            $payment = Payment::factory()->create([
                'status' => $status,
                'confirmed_at' => $confirmedAt,
            ]);

            $this->assertSame($status, $payment->fresh()->status);
        }
    }

    public function test_webhook_events_relationship(): void
    {
        $payment = Payment::factory()->create();
        $event = PaymentWebhookEvent::factory()->forPayment($payment)->create();

        $this->assertTrue($event->payment->is($payment));
        $this->assertTrue($payment->fresh()->webhookEvents->contains($event));
    }
}
