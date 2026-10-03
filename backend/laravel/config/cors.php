<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Guest-Cart-Id', 'Idempotency-Key', 'X-Requested-With', 'X-Upload-Token'],
    'exposed_headers' => ['X-Guest-Cart-Id', 'X-Request-Id', 'X-Upload-Token'],
    'max_age' => 86400,
    'supports_credentials' => true,
];
