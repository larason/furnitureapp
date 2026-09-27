<?php

$positiveInteger = static function (string $key, int $default): int {
    $value = env($key, $default);

    if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $key));
    }

    return (int) $value;
};

return [
    'pre_auth_requests_per_minute' => $positiveInteger('RATE_LIMIT_PRE_AUTH_PER_MINUTE', 300),
    'max_authorization_header_bytes' => $positiveInteger('MAX_AUTHORIZATION_HEADER_BYTES', 4096),
    'max_request_body_bytes' => $positiveInteger('MAX_REQUEST_BODY_BYTES', 6291456),
    'max_json_body_bytes' => $positiveInteger('MAX_JSON_BODY_BYTES', 262144),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
    'guest_cart_stale_days' => $positiveInteger('GUEST_CART_STALE_DAYS', 30),
];
