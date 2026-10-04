<?php

namespace App\Http\Requests\Concerns;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Carbon\CarbonImmutable;

trait ParsesIso8601UtcQueryDate
{
    abstract protected function error(ApiErrorCode $code, string $field, string $message): ApiException;

    private function parseIso8601UtcQueryDate(string $value, string $field): CarbonImmutable
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?Z$/', $value, $matches) !== 1) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, $field, "The {$field} parameter must be ISO8601 UTC.");
        }

        $hasFraction = isset($matches[2]);
        $normalized = $matches[1].($hasFraction ? '.'.str_pad($matches[2], 6, '0') : '').'Z';
        $format = $hasFraction ? '!Y-m-d\TH:i:s.u\Z' : '!Y-m-d\TH:i:s\Z';
        $date = CarbonImmutable::createFromFormat($format, $normalized, 'UTC');
        $errors = CarbonImmutable::getLastErrors();

        if ($date === null || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, $field, "The {$field} parameter must be ISO8601 UTC.");
        }

        return $date;
    }
}
