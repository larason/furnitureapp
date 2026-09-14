<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Server-generated business references (e.g. ENQ-XXXXXXXXXX).
 *
 * Domain-owned CSPRNG generation owned by the application layer, so models
 * never depend on factory (test-support) infrastructure for server defaults.
 */
class ReferenceGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function generate(string $prefix, int $length): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Reference suffix length must be positive.');
        }

        $suffix = '';

        for ($i = 0; $i < $length; $i++) {
            $suffix .= self::ALPHABET[random_int(0, 35)];
        }

        return $prefix.$suffix;
    }
}
