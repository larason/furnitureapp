<?php

$positiveInteger = static function (string $key, int $default): int {
    $value = env($key, $default);

    if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $key));
    }

    return (int) $value;
};

return [
    'authenticated_read_per_minute' => $positiveInteger('RATE_LIMIT_AUTHENTICATED_READ_PER_MINUTE', 180),
    'authenticated_write_per_minute' => $positiveInteger('RATE_LIMIT_AUTHENTICATED_WRITE_PER_MINUTE', 60),
    // Token registration is idempotent, so a low budget is safe and keeps a
    // compromised client from churning device rows.
    'device_registration_per_minute' => $positiveInteger('RATE_LIMIT_DEVICE_REGISTRATION_PER_MINUTE', 20),
];
