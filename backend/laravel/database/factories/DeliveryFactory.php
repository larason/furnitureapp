<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    public function definition(): array
    {
        $order = Order::factory()->deliveryFinalized()->create();

        return [
            'order_id' => $order->id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $order->delivery_address,
            'delivery_instructions' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $order->delivery_address,
        ]);
    }

    public function withInstructions(string $instructions): static
    {
        return $this->state(fn () => [
            'delivery_instructions' => $instructions,
        ]);
    }
}
