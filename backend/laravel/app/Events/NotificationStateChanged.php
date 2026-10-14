<?php

namespace App\Events;

use App\Models\Notification;
use App\Support\NotificationIdentifier;
use App\Support\NotificationRealtimeChange;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NotificationStateChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public const CHANNEL_PREFIX = 'notifications.';

    public int $tries = 3;

    public function __construct(
        private readonly Notification $notification,
        private readonly NotificationRealtimeChange $change,
    ) {}

    public static function channelName(int $recipientUserId): string
    {
        return self::CHANNEL_PREFIX.$recipientUserId;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel(self::channelName((int) $this->notification->recipient_user_id));
    }

    public function broadcastAs(): string
    {
        return 'notification.state-changed';
    }

    /** @return array{notification_id: string, change: string} */
    public function broadcastWith(): array
    {
        return [
            'notification_id' => NotificationIdentifier::encodeId((int) $this->notification->getKey()),
            'change' => $this->change->value,
        ];
    }

    public function broadcastQueue(): string
    {
        return (string) config('notifications.realtime.queue');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }
}
