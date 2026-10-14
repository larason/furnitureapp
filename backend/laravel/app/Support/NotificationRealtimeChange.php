<?php

namespace App\Support;

enum NotificationRealtimeChange: string
{
    case Created = 'created';
    case ReadStateChanged = 'read_state_changed';
}
