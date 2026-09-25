<?php

namespace App\Support;

use DomainException;

final class AddressField
{
    public const ADDRESS_LINE = 'address_line';

    public const CITY = 'city';

    private const ALLOWED_KEYS = [
        self::ADDRESS_LINE,
        self::CITY,
    ];

    public static function validate(array $address): void
    {
        self::normalize($address);
    }

    /**
     * @return array{address_line: string, city: string}
     */
    public static function normalizeSnapshot(array $address): array
    {
        $legacyRegion = null;

        if (array_key_exists('region', $address)) {
            $legacyRegion = self::normalizeRequiredString('region', $address['region']);
            unset($address['region']);
        }

        if (array_key_exists('postal_code', $address)) {
            if ($address['postal_code'] !== null) {
                self::normalizeRequiredString('postal_code', $address['postal_code']);
            }

            unset($address['postal_code']);
        }

        if ($legacyRegion !== null) {
            $address['city'] = self::normalizeRequiredString('city', $address['city'] ?? $legacyRegion);
        }

        return self::normalize($address);
    }

    /**
     * @return array{address_line: string, city: string}
     */
    public static function normalize(array $address): array
    {
        $unknown = array_diff(array_keys($address), self::ALLOWED_KEYS);

        if ($unknown !== []) {
            throw new DomainException('Delivery address contains unsupported fields.');
        }

        $normalized = [];

        foreach (self::ALLOWED_KEYS as $key) {
            $normalized[$key] = self::normalizeRequiredString($key, $address[$key] ?? null);
        }

        return $normalized;
    }

    private static function normalizeRequiredString(string $key, mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException("Delivery address {$key} is required.");
        }

        $normalized = trim($value);

        if ($normalized === '') {
            throw new DomainException("Delivery address {$key} is required.");
        }

        return $normalized;
    }
}
