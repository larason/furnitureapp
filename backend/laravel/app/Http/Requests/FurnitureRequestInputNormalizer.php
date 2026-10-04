<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreatesStaticApiValidationError;
use App\Models\FurnitureRequest;
use App\Services\Requests\FurnitureRequestInput;
use App\Support\ApiErrorCode;
use App\Support\PhoneNumber;
use App\Support\ProductIdentifier;
use App\Support\RequestField;

/**
 * Explicit REQ-001 field normalization and shape validation.
 *
 * Extracted from CreateFurnitureRequestRequest so each class stays small and
 * single-purpose. Validation is explicit instead of Laravel-rule driven
 * because the frozen contract needs strict scalar typing, nested dimension key
 * checks, and exact error codes the framework's rule-name heuristics cannot
 * express. Product existence/eligibility stays a domain concern.
 */
final class FurnitureRequestInputNormalizer
{
    use CreatesStaticApiValidationError;

    private const MEASUREMENT_FIELDS = [
        RequestField::LENGTH,
        RequestField::WIDTH,
        RequestField::HEIGHT,
    ];

    private const DIMENSION_FIELDS = [
        RequestField::LENGTH,
        RequestField::WIDTH,
        RequestField::HEIGHT,
        RequestField::UNIT,
    ];

    private const CHARACTERS_SUFFIX = ' characters.';

    /**
     * Multipart fields arrive as strings; the documented structured encoding is
     * `dimensions[length|width|height|unit]`. Only numeric measurement strings
     * are decoded (to int/float); anything else is left for the strict validator
     * to reject.
     *
     * @param  array<string, mixed>  $dimensions
     * @return array<string, mixed>
     */
    public static function decodeMultipartDimensions(array $dimensions): array
    {
        foreach (self::MEASUREMENT_FIELDS as $key) {
            if (isset($dimensions[$key]) && is_string($dimensions[$key]) && is_numeric($dimensions[$key])) {
                $dimensions[$key] = preg_match('/^[+-]?\d+$/', $dimensions[$key]) === 1
                    ? (int) $dimensions[$key]
                    : (float) $dimensions[$key];
            }
        }

        return $dimensions;
    }

    /** @param array<string, mixed> $input */
    public static function normalizeProductId(array $input): ?string
    {
        $value = $input['product_id'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'product_id', 'The product_id field must be a string.');
        }

        if (ProductIdentifier::decode($value) === null) {
            throw self::error(ApiErrorCode::INVALID_FORMAT, 'product_id', 'The product_id field must be a valid product reference.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    public static function normalizeQuantity(array $input): ?int
    {
        $value = $input['quantity'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'quantity', 'The quantity field must be an integer.');
        }

        if ($value < 1 || $value > FurnitureRequest::MAX_QUANTITY) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'quantity', 'The quantity field must be between 1 and '.FurnitureRequest::MAX_QUANTITY.'.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    public static function normalizeName(array $input): string
    {
        $value = $input['name'] ?? null;

        if ($value === null) {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'name', 'The name field is required.');
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'name', 'The name field must be a string.');
        }

        $name = trim(preg_replace('/\s+/u', ' ', trim($value)) ?? $value);

        if ($name === '') {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'name', 'The name field is required.');
        }

        if (mb_strlen($name) > FurnitureRequest::MAX_NAME) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'name', 'The name field must not be greater than '.FurnitureRequest::MAX_NAME.self::CHARACTERS_SUFFIX);
        }

        return $name;
    }

    /** @param array<string, mixed> $input */
    public static function normalizePhone(array $input): ?string
    {
        $value = $input['phone'] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'phone', 'The phone field must be a string.');
        }

        $phone = trim($value);

        if (mb_strlen($phone) > PhoneNumber::MAX_LENGTH) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'phone', 'The phone field must not be greater than '.PhoneNumber::MAX_LENGTH.self::CHARACTERS_SUFFIX);
        }

        if (! PhoneNumber::isWellFormed($phone)) {
            throw self::error(ApiErrorCode::INVALID_FORMAT, 'phone', 'The phone field format is invalid.');
        }

        return $phone;
    }

    /** @param array<string, mixed> $input */
    public static function normalizeEmail(array $input): ?string
    {
        $value = $input['email'] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'email', 'The email field must be a string.');
        }

        $email = mb_strtolower(trim($value));

        if (mb_strlen($email) > FurnitureRequest::MAX_EMAIL) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'email', 'The email field must not be greater than '.FurnitureRequest::MAX_EMAIL.self::CHARACTERS_SUFFIX);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw self::error(ApiErrorCode::INVALID_FORMAT, 'email', 'The email field format is invalid.');
        }

        return $email;
    }

    /** @param array<string, mixed> $input */
    public static function normalizeOptionalText(array $input, string $field, int $maxLength): ?string
    {
        $value = $input[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, $field, "The {$field} field must be a string.");
        }

        $text = trim($value);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $maxLength) {
            throw self::error(ApiErrorCode::INVALID_VALUE, $field, "The {$field} field must not be greater than {$maxLength} characters.");
        }

        return $text;
    }

    /** @param array<string, mixed> $input */
    public static function normalizeDimensions(array $input): ?array
    {
        $value = $input['dimensions'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_array($value) || array_is_list($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'dimensions', 'The dimensions field must be an object.');
        }

        self::assertDimensionKeys($value);

        $normalized = [RequestField::UNIT => self::normalizeDimensionUnit($value)];

        foreach (self::MEASUREMENT_FIELDS as $field) {
            $dimension = $value[$field] ?? null;

            if ($dimension === null) {
                continue;
            }

            $normalized[$field] = self::normalizeDimensionValue($field, $dimension);
        }

        if (count($normalized) === 1) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'dimensions', 'The dimensions field must include at least one measurement.');
        }

        return $normalized;
    }

    public static function assertContactChannel(FurnitureRequestInput $input): void
    {
        if ($input->phone === null && $input->email === null) {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'phone', 'At least one of phone or email is required.');
        }
    }

    /** @param array<string, mixed> $value */
    private static function assertDimensionKeys(array $value): void
    {
        $unknown = array_diff(array_keys($value), self::DIMENSION_FIELDS);

        if ($unknown !== []) {
            $key = (string) reset($unknown);

            throw self::error(ApiErrorCode::INVALID_VALUE, 'dimensions.'.$key, "The dimensions.{$key} field is not supported.");
        }
    }

    /** @param array<string, mixed> $value */
    private static function normalizeDimensionUnit(array $value): string
    {
        $unit = $value[RequestField::UNIT] ?? null;

        if ($unit === null) {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'dimensions.unit', 'The dimensions.unit field is required.');
        }

        if (! is_string($unit)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'dimensions.unit', 'The dimensions.unit field must be a string.');
        }

        if ($unit !== RequestField::UNIT_CM) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'dimensions.unit', 'The dimensions.unit field must be cm.');
        }

        return $unit;
    }

    private static function normalizeDimensionValue(string $field, mixed $value): int|float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'dimensions.'.$field, "The dimensions.{$field} field must be a number.");
        }

        if ($value <= 0 || $value > RequestField::MAX_DIMENSION) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'dimensions.'.$field, "The dimensions.{$field} field must be a positive number up to ".RequestField::MAX_DIMENSION.'.');
        }

        return $value;
    }
}
