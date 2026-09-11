<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Support\OrderActorType;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => null,
            'to_status' => OrderStatus::PENDING_PAYMENT,
            'actor_type' => OrderActorType::SYSTEM,
            'actor_id' => null,
            'customer_note' => null,
            'internal_note' => null,
            'occurred_at' => now(),
        ];
    }

    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => null,
            'to_status' => OrderStatus::PENDING_PAYMENT,
            'actor_type' => OrderActorType::SYSTEM,
            'actor_id' => null,
        ]);
    }

    public function transition(OrderStatus $from, OrderStatus $to): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => OrderActorType::SYSTEM,
            'actor_id' => null,
        ]);
    }

    public function byUser(User $user, OrderActorType $type = OrderActorType::STAFF): static
    {
        if ($type === OrderActorType::SYSTEM) {
            throw new \InvalidArgumentException('System events cannot have a user actor.');
        }

        return $this->state(fn (array $attributes) => [
            'actor_type' => $type,
            'actor_id' => $user->id,
        ]);
    }

    public function withCustomerNote(string $note): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_note' => $note,
        ]);
    }

    public function withInternalNote(string $note): static
    {
        return $this->state(fn (array $attributes) => [
            'internal_note' => $note,
        ]);
    }

    public function at(Carbon $occurredAt): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => $occurredAt,
        ]);
    }
}
