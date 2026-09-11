<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    private static array $usedReferences = [];

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10000, 50000000);

        return [
            'customer_id' => User::factory(),
            'order_reference' => self::generateReference(),
            'status' => OrderStatus::PENDING_PAYMENT,
            'fulfillment_type' => FulfillmentType::PICKUP,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => $subtotal,
            'delivery_fee_amount' => 0,
            'total_amount' => $subtotal,
            'recipient_name' => fake()->name(),
            'recipient_phone' => '+2557'.fake()->numerify('########'),
            'delivery_address' => null,
        ];
    }

    public function pickup(): static
    {
        return $this->state(function (array $attributes) {
            $subtotal = $attributes['subtotal_amount'] ?? fake()->numberBetween(10000, 50000000);

            return [
                'fulfillment_type' => FulfillmentType::PICKUP,
                'delivery_fee_status' => DeliveryFeeStatus::FINALIZED,
                'subtotal_amount' => $subtotal,
                'delivery_fee_amount' => 0,
                'total_amount' => $subtotal,
                'delivery_address' => null,
            ];
        });
    }

    public function deliveryPending(): static
    {
        return $this->state(function (array $attributes) {
            $subtotal = $attributes['subtotal_amount'] ?? fake()->numberBetween(10000, 50000000);

            return [
                'fulfillment_type' => FulfillmentType::DELIVERY,
                'delivery_fee_status' => DeliveryFeeStatus::PENDING,
                'subtotal_amount' => $subtotal,
                'delivery_fee_amount' => null,
                'total_amount' => $subtotal,
                'delivery_address' => self::deliveryAddress(),
            ];
        });
    }

    public function deliveryFinalized(?int $deliveryFee = null): static
    {
        return $this->state(function (array $attributes) use ($deliveryFee) {
            $subtotal = $attributes['subtotal_amount'] ?? fake()->numberBetween(10000, 50000000);
            $fee = $deliveryFee ?? fake()->numberBetween(0, 50000);

            return [
                'fulfillment_type' => FulfillmentType::DELIVERY,
                'delivery_fee_status' => DeliveryFeeStatus::FINALIZED,
                'subtotal_amount' => $subtotal,
                'delivery_fee_amount' => $fee,
                'total_amount' => $subtotal + $fee,
                'delivery_address' => self::deliveryAddress(),
            ];
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = Order::REFERENCE_PREFIX.strtoupper(fake()->bothify('?????'));
        } while (isset(self::$usedReferences[$reference]));

        self::$usedReferences[$reference] = true;

        return $reference;
    }

    private static function deliveryAddress(): array
    {
        return [
            'address_line' => fake()->streetAddress(),
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'postal_code' => null,
        ];
    }
}
