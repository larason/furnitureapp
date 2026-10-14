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
    'realtime' => [
        'queue' => env('NOTIFICATION_BROADCAST_QUEUE', 'notifications-broadcast'),
    ],
    'push' => [
        // Fan-out of customer business-event notifications. Stays off until a
        // notification event and recipient policy is separately approved.
        'enabled' => (bool) env('NOTIFICATION_PUSH_ENABLED', false),
        // Token management is authorized independently of fan-out so devices can
        // be registered and retired without any notification being sent.
        'registration_enabled' => (bool) env('NOTIFICATION_PUSH_REGISTRATION_ENABLED', false),
        'diagnostic_enabled' => (bool) env('NOTIFICATION_PUSH_DIAGNOSTIC_ENABLED', false),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'timeout_seconds' => (int) env('NOTIFICATION_PUSH_TIMEOUT_SECONDS', 10),
        'max_attempts' => (int) env('NOTIFICATION_PUSH_MAX_ATTEMPTS', 3),
        'stale_after_days' => (int) env('NOTIFICATION_PUSH_STALE_DEVICE_DAYS', 90),
        // Overrides the application key as the HMAC key for token fingerprints.
        // Rotating it requires every stored device row to be re-registered.
        'token_fingerprint_key' => env('PUSH_TOKEN_FINGERPRINT_KEY'),
    ],
];
