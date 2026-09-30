<?php

namespace App\Support;

final class PhoneNumber
{
    public const MAX_LENGTH = 30;

    public const PATTERN = '/^\+?[0-9][0-9 ().-]{6,29}$/';

    public static function isWellFormed(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }
}
