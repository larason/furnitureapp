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
];
