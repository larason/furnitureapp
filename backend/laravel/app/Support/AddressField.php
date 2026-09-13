<?php

namespace App\Support;

use DomainException;

final class AddressField
{
    public const ADDRESS_LINE = 'address_line';

    public const CITY = 'city';

    public const REGION = 'region';

    public const POSTAL_CODE = 'postal_code';

    private const ALLOWED_KEYS = [
        self::ADDRESS_LINE,
        self::CITY,
        self::REGION,
        self::POSTAL_CODE,
    ];

    public static function validate(array $address): void
    {
        $unknown = array_diff(array_keys($address), self::ALLOWED_KEYS);

        if ($unknown !== []) {
            throw new DomainException('Delivery address contains unsupported fields.');
        }

        foreach ([self::ADDRESS_LINE, self::CITY, self::REGION] as $key) {
            self::assertNonEmptyString($key, $address[$key] ?? null);
        }

        if (array_key_exists(self::POSTAL_CODE, $address) && $address[self::POSTAL_CODE] !== null) {
            self::assertNonEmptyString(self::POSTAL_CODE, $address[self::POSTAL_CODE]);
        }
    }

    private static function assertNonEmptyString(string $key, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Delivery address {$key} is required.");
        }
    }
}
