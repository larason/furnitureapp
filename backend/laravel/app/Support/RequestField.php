<?php

namespace App\Support;

use DomainException;

final class RequestField
{
    public const PRODUCT_NAME = 'product_name';

    public const DESCRIPTION = 'description';

    public const REFERENCE = 'reference';

    public const LENGTH = 'length';

    public const WIDTH = 'width';

    public const HEIGHT = 'height';

    public const UNIT = 'unit';

    public const UNIT_CM = 'cm';

    public const MAX_DIMENSION = 10000;

    public const MAX_DETAIL_VALUE = 5000;

    private const PRODUCT_DETAILS_KEYS = [
        self::PRODUCT_NAME,
        self::DESCRIPTION,
        self::REFERENCE,
    ];

    private const PRODUCT_DETAILS_REQUIRED = [
        self::PRODUCT_NAME,
        self::DESCRIPTION,
    ];

    private const DIMENSIONS_KEYS = [
        self::LENGTH,
        self::WIDTH,
        self::HEIGHT,
        self::UNIT,
    ];

    public static function validateProductDetails(array $details): void
    {
        $unknown = array_diff(array_keys($details), self::PRODUCT_DETAILS_KEYS);

        if ($unknown !== []) {
            throw new DomainException('Product details contain unsupported fields.');
        }

        foreach (self::PRODUCT_DETAILS_REQUIRED as $key) {
            self::assertRequiredDetailValue($key, $details[$key] ?? null);
        }

        self::assertOptionalDetailValue(self::REFERENCE, $details[self::REFERENCE] ?? null);
    }

    private static function assertRequiredDetailValue(string $key, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Product details {$key} is required.");
        }

        self::assertDetailLength($key, $value);
    }

    private static function assertOptionalDetailValue(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            throw new DomainException("Product details {$key} must be a string or null.");
        }

        self::assertDetailLength($key, $value);
    }

    private static function assertDetailLength(string $key, string $value): void
    {
        if (mb_strlen(trim($value)) > self::MAX_DETAIL_VALUE) {
            throw new DomainException("Product details {$key} is too long.");
        }
    }

    public static function validateDimensions(array $dimensions): void
    {
        $unknown = array_diff(array_keys($dimensions), self::DIMENSIONS_KEYS);

        if ($unknown !== []) {
            throw new DomainException('Dimensions contain unsupported fields.');
        }

        if (($dimensions[self::UNIT] ?? null) !== self::UNIT_CM) {
            throw new DomainException('Dimensions unit must be cm.');
        }

        foreach ([self::LENGTH, self::WIDTH, self::HEIGHT] as $key) {
            self::assertPositiveDimension($key, $dimensions[$key] ?? null);
        }
    }

    private static function assertPositiveDimension(string $key, mixed $value): void
    {
        if (! is_numeric($value) || $value <= 0 || $value > self::MAX_DIMENSION) {
            throw new DomainException("Dimensions {$key} must be a positive number up to ".self::MAX_DIMENSION.'.');
        }
    }
}
