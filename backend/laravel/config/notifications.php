<?php

return [
    'email' => [
        'enabled' => (bool) env('NOTIFICATION_EMAIL_ENABLED', false),
        'mailer' => env('NOTIFICATION_EMAIL_MAILER', 'resend'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME', 'SL Furnitures'),
        'reply_to_address' => env('MAIL_REPLY_TO_ADDRESS'),
        'reply_to_name' => env('MAIL_REPLY_TO_NAME', 'SL Furnitures'),
        'max_attempts' => (int) env('NOTIFICATION_EMAIL_MAX_ATTEMPTS', 3),
        'stale_after_minutes' => (int) env('NOTIFICATION_EMAIL_STALE_AFTER_MINUTES', 15),
    ],
];
