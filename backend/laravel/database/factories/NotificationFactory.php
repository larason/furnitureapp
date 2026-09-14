<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipient_user_id' => User::factory(),
            'type' => NotificationType::ORDER_RECEIVED,
            'title' => 'Order received',
            'message' => 'Your order has been received and is being reviewed.',
            'target' => null,
            'source_type' => null,
            'source_id' => null,
            'read_at' => null,
        ];
    }

    public function forRecipient(User $user): static
    {
        return $this->state(fn () => ['recipient_user_id' => $user->id]);
    }

    public function orderNotification(NotificationType $type = NotificationType::ORDER_RECEIVED): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'title' => $this->titleFor($type),
            'message' => $this->messageFor($type),
        ]);
    }

    public function operationalNotification(NotificationType $type = NotificationType::NEW_ORDER): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'title' => $this->titleFor($type),
            'message' => $this->messageFor($type),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn () => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => Carbon::now()]);
    }

    public function withTarget(string $targetType, string $targetId): static
    {
        return $this->state(fn () => [
            'target' => [
                Notification::TARGET_KEY_TYPE => $targetType,
                Notification::TARGET_KEY_ID => $targetId,
            ],
        ]);
    }

    public function withSource(string $sourceType, string $sourceId): static
    {
        return $this->state(fn () => [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    private function titleFor(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::ORDER_RECEIVED => 'Order received',
            NotificationType::ORDER_ACCEPTED => 'Order accepted',
            NotificationType::ORDER_PROCESSING => 'Order being processed',
            NotificationType::ORDER_READY_FOR_PICKUP => 'Order ready for pickup',
            NotificationType::ORDER_SHIPPED => 'Order shipped',
            NotificationType::ORDER_DELIVERED => 'Order delivered',
            NotificationType::ORDER_COMPLETED => 'Order completed',
            NotificationType::ORDER_CANCELLED => 'Order cancelled',
            NotificationType::NEW_ORDER => 'New order received',
            NotificationType::NEW_MADE_TO_ORDER_REQUEST => 'New made-to-order request',
            NotificationType::NEW_ENQUIRY => 'New enquiry received',
        };
    }

    private function messageFor(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::ORDER_RECEIVED => 'Your order has been received and is being reviewed.',
            NotificationType::ORDER_ACCEPTED => 'Your order has been accepted.',
            NotificationType::ORDER_PROCESSING => 'Your order is being processed.',
            NotificationType::ORDER_READY_FOR_PICKUP => 'Your order is ready for pickup.',
            NotificationType::ORDER_SHIPPED => 'Your order has been shipped.',
            NotificationType::ORDER_DELIVERED => 'Your order has been delivered.',
            NotificationType::ORDER_COMPLETED => 'Your order has been completed.',
            NotificationType::ORDER_CANCELLED => 'Your order has been cancelled.',
            NotificationType::NEW_ORDER => 'A new order has been placed.',
            NotificationType::NEW_MADE_TO_ORDER_REQUEST => 'A new made-to-order request has been submitted.',
            NotificationType::NEW_ENQUIRY => 'A new enquiry has been received.',
        };
    }
}
