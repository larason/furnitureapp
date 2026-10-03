<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

final class ListCustomerHistoryRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 20;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /** @return array{page: int, per_page: int} */
    public function pagination(): array
    {
        $unknown = array_diff(array_keys($this->query()), ['page', 'per_page']);
        if ($unknown !== []) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, (string) reset($unknown), 'The query parameter is not supported.');
        }

        return [
            'page' => $this->positiveInteger('page', 1),
            'per_page' => $this->positiveInteger('per_page', self::DEFAULT_PER_PAGE, 100),
        ];
    }

    private function positiveInteger(string $field, int $default, int $maximum = PHP_INT_MAX): int
    {
        $value = $this->query($field);

        if ($value === null) {
            return $default;
        }

        if (! is_string($value) || preg_match('/^\d{1,9}$/', $value) !== 1) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, $field, "The {$field} parameter must be an integer.");
        }

        $integer = (int) $value;
        if ($integer < 1 || $integer > $maximum) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, $field, "The {$field} parameter is outside the allowed range.");
        }

        return $integer;
    }

    private function error(ApiErrorCode $code, string $field, string $message): ApiException
    {
        return new ApiException($code, $message, 422, $field);
    }
}
