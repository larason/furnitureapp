<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreatesApiValidationError;
use App\Http\Requests\Concerns\ParsesIso8601UtcQueryDate;
use App\Http\Requests\Concerns\ParsesPositiveQueryInteger;
use App\Services\Enquiries\OperationalEnquiryQuery;
use App\Support\ApiErrorCode;
use App\Support\EnquiryCategory;
use App\Support\EnquiryStatus;
use App\Support\OrderIdentifier;
use App\Support\ProductIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ListOperationalEnquiriesRequest extends FormRequest
{
    use CreatesApiValidationError;
    use ParsesIso8601UtcQueryDate;
    use ParsesPositiveQueryInteger;

    private const ALLOWED_FIELDS = [
        'search', 'enquiry_status', 'category', 'product_id', 'order_id',
        'created_from', 'created_to', 'page', 'per_page',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function normalizedQuery(): OperationalEnquiryQuery
    {
        $input = $this->query();
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);
        if ($unknown !== []) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, (string) reset($unknown), 'The query parameter is not supported.');
        }

        $createdFrom = $this->parseDate($input, 'created_from');
        $createdTo = $this->parseDate($input, 'created_to');
        if ($createdFrom !== null && $createdTo !== null && $createdFrom->greaterThan($createdTo)) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'created_from', 'The created_from value must not be later than created_to.');
        }

        return new OperationalEnquiryQuery(
            search: $this->search($input),
            status: $this->parseEnum($input, 'enquiry_status', EnquiryStatus::class),
            category: $this->parseEnum($input, 'category', EnquiryCategory::class),
            productId: $this->opaqueId($input, 'product_id', ProductIdentifier::class),
            orderPublicId: $this->orderPublicId($input),
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            page: $this->optionalPositiveQueryInteger($input, 'page', 1),
            perPage: $this->optionalPositiveQueryInteger($input, 'per_page', 20, 100),
        );
    }

    /** @param array<string, mixed> $input */
    private function search(array $input): ?string
    {
        if (! array_key_exists('search', $input)) {
            return null;
        }

        if (! is_string($input['search'])) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, 'search', 'The search parameter must be a string.');
        }

        $value = trim($input['search']);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 100) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'search', 'The search parameter must not be greater than 100 characters.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function opaqueId(array $input, string $field, string $identifierClass): ?int
    {
        if (! array_key_exists($field, $input)) {
            return null;
        }

        $value = $input[$field];
        if (! is_string($value)) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, $field, "The {$field} parameter must be a string.");
        }

        $id = $identifierClass::decode($value);
        if ($id === null) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, $field, "The {$field} parameter must be a valid reference.");
        }

        return $id;
    }

    /** @param array<string, mixed> $input */
    private function orderPublicId(array $input): ?string
    {
        if (! array_key_exists('order_id', $input)) {
            return null;
        }

        if (! is_string($input['order_id'])) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, 'order_id', 'The order_id parameter must be a string.');
        }

        $id = OrderIdentifier::decode($input['order_id']);
        if ($id === null) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, 'order_id', 'The order_id parameter must be a valid reference.');
        }

        return $id;
    }

    /** @param array<string, mixed> $input */
    private function parseEnum(array $input, string $field, string $enumClass): mixed
    {
        if (! array_key_exists($field, $input)) {
            return null;
        }

        if (! is_string($input[$field])) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, $field, "The {$field} parameter must be a string.");
        }

        $value = $enumClass::tryFrom($input[$field]);
        if ($value === null) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, $field, "The {$field} parameter is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function parseDate(array $input, string $field): ?CarbonImmutable
    {
        if (! array_key_exists($field, $input)) {
            return null;
        }

        $value = $input[$field];
        if (! is_string($value)) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, $field, "The {$field} parameter must be ISO8601 UTC.");
        }

        return $this->parseIso8601UtcQueryDate($value, $field);
    }
}
