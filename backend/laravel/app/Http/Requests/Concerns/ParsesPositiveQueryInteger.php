<?php

namespace App\Http\Requests\Concerns;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;

trait ParsesPositiveQueryInteger
{
    abstract protected function error(ApiErrorCode $code, string $field, string $message): ApiException;

    /** @param array<string, mixed> $input */
    private function optionalPositiveQueryInteger(array $input, string $field, int $default, int $maximum = PHP_INT_MAX): int
    {
        if (! array_key_exists($field, $input)) {
            return $default;
        }

        return $this->parsePositiveQueryInteger($input[$field], $field, $maximum);
    }

    private function parsePositiveQueryInteger(mixed $value, string $field, int $maximum = PHP_INT_MAX): int
    {
        if (! is_string($value) || preg_match('/^\d{1,9}$/', $value) !== 1) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, $field, "The {$field} parameter must be an integer.");
        }

        $integer = (int) $value;
        if ($integer < 1 || $integer > $maximum) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, $field, "The {$field} parameter is outside the allowed range.");
        }

        return $integer;
    }
}
