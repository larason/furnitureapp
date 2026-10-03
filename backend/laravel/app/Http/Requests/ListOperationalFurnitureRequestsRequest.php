<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Services\Requests\OperationalFurnitureRequestQuery;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use App\Support\RequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ListOperationalFurnitureRequestsRequest extends FormRequest
{
    private const ALLOWED_FIELDS = [
        'search',
        'request_status',
        'product_id',
        'created_from',
        'created_to',
        'page',
        'per_page',
    ];

    private const DEFAULT_PER_PAGE = 20;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function normalizedQuery(): OperationalFurnitureRequestQuery
    {
        $input = $this->query();
        $this->rejectUnknownFields($input);

        $createdFrom = $this->parseDate($input, 'created_from');
        $createdTo = $this->parseDate($input, 'created_to');

        if ($createdFrom !== null && $createdTo !== null && $createdFrom->greaterThan($createdTo)) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'created_from', 'The created_from value must not be later than created_to.');
        }

        return new OperationalFurnitureRequestQuery(
            search: $this->search($input),
            requestStatus: $this->status($input),
            productId: $this->productId($input),
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            page: $this->positiveInteger($input, 'page', 1),
            perPage: $this->positiveInteger($input, 'per_page', self::DEFAULT_PER_PAGE, 100),
        );
    }

    /** @param array<string, mixed> $input */
    private function rejectUnknownFields(array $input): void
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            $field = (string) reset($unknown);

            throw $this->error(ApiErrorCode::INVALID_VALUE, $field, 'The query parameter is not supported.');
        }
    }

    /** @param array<string, mixed> $input */
    private function search(array $input): ?string
    {
        if (! array_key_exists('search', $input)) {
            return null;
        }

        $value = $input['search'];

        if (! is_string($value)) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, 'search', 'The search parameter must be a string.');
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 100) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'search', 'The search parameter must not be greater than 100 characters.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function status(array $input): ?RequestStatus
    {
        if (! array_key_exists('request_status', $input)) {
            return null;
        }

        $value = $input['request_status'];

        if (! is_string($value)) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, 'request_status', 'The request_status parameter must be a string.');
        }

        $status = RequestStatus::tryFrom($value);

        if ($status === null) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'request_status', 'The request_status parameter is invalid.');
        }

        return $status;
    }

    /** @param array<string, mixed> $input */
    private function productId(array $input): ?int
    {
        if (! array_key_exists('product_id', $input)) {
            return null;
        }

        $value = $input['product_id'];

        if (! is_string($value) || ProductIdentifier::decode($value) !== null) {
            if (! is_string($value)) {
                throw $this->error(ApiErrorCode::INVALID_TYPE, 'product_id', 'The product_id parameter must be a string.');
            }

            return ProductIdentifier::decode($value);
        }

        throw $this->error(ApiErrorCode::INVALID_FORMAT, 'product_id', 'The product_id parameter must be a valid product reference.');
    }

    /** @param array<string, mixed> $input */
    private function parseDate(array $input, string $field): ?CarbonImmutable
    {
        if (! array_key_exists($field, $input)) {
            return null;
        }

        $value = $input[$field];

        if (! is_string($value)) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, $field, "The {$field} parameter must be a string.");
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, $field, "The {$field} parameter must be ISO8601 UTC.");
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC');
        $errors = CarbonImmutable::getLastErrors();

        if ($date === null || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, $field, "The {$field} parameter must be ISO8601 UTC.");
        }

        return $date;
    }

    /** @param array<string, mixed> $input */
    private function positiveInteger(array $input, string $field, int $default, int $maximum = PHP_INT_MAX): int
    {
        if (! array_key_exists($field, $input)) {
            return $default;
        }

        $value = $input[$field];

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
