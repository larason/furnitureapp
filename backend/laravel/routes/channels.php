<?php

use App\Events\NotificationStateChanged;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    NotificationStateChanged::CHANNEL_PREFIX.'{recipientUserId}',
    function (User $user, string $recipientUserId): bool {
        return ctype_digit($recipientUserId)
            && (int) $user->getKey() === (int) $recipientUserId;
    },
);
