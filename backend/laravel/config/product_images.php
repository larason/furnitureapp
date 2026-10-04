<?php

return [
    'disk' => env('PRODUCT_IMAGE_DISK', 'r2'),
    'max_bytes' => (int) env('PRODUCT_IMAGE_MAX_BYTES', 5 * 1024 * 1024),
    'public_base_url' => env('R2_PUBLIC_BASE_URL'),
    'allowed_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];
