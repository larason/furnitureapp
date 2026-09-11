<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    private static array $usedReferences = [];

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'order_id' => $order->id,
            'payment_reference' => self::generateReference(),
            'provider' => 'internal',
            'method' => 'manual',
            'status' => PaymentStatus::PENDING,
            'amount' => $order->total_amount ?? 0,
            'currency' => $order->currency ?? Payment::CURRENCY_TZS,
            'provider_transaction_id' => null,
            'provider_reference' => null,
            'failure_code' => null,
            'failure_message' => null,
            'initiated_at' => now(),
            'confirmed_at' => null,
            'expires_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::PENDING,
            'confirmed_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::PROCESSING,
            'confirmed_at' => null,
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::SUCCEEDED,
            'confirmed_at' => now(),
            'provider_transaction_id' => 'TXN-'.fake()->numerify('########'),
        ]);
    }

    public function failed(?string $code = null, ?string $message = null): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::FAILED,
            'confirmed_at' => null,
            'failure_code' => $code ?? 'PAYMENT_DECLINED',
            'failure_message' => $message ?? 'Payment was declined.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::CANCELLED,
            'confirmed_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::EXPIRED,
            'confirmed_at' => null,
        ]);
    }

    public static function generateReference(): string
    {
        do {
            $reference = Payment::REFERENCE_PREFIX.strtoupper(fake()->bothify('????????'));
        } while (isset(self::$usedReferences[$reference]));

        self::$usedReferences[$reference] = true;

        return $reference;
    }
}
