<?php

$positiveInteger = static function (string $key, int $default): int {
    $value = env($key, $default);

    if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $key));
    }

    return (int) $value;
};

return [
    'retention_hours' => $positiveInteger('IDEMPOTENCY_RETENTION_HOURS', 24),
];
