<?php

namespace App\Support;

use App\Models\Enquiry;

final class EnquiryIdentifier
{
    private const PREFIX = 'enq_';

    public static function encode(Enquiry $enquiry): string
    {
        return self::encodeId((int) $enquiry->getKey());
    }

    public static function encodeId(int $id): string
    {
        return self::PREFIX.base_convert((string) $id, 10, 36);
    }

    public static function decode(string $identifier): ?int
    {
        if (! str_starts_with($identifier, self::PREFIX)) {
            return null;
        }

        $value = substr($identifier, strlen(self::PREFIX));

        if ($value === '' || preg_match('/^[0-9a-z]+$/', $value) !== 1) {
            return null;
        }

        $decoded = base_convert($value, 36, 10);

        return ctype_digit($decoded) && (int) $decoded > 0 ? (int) $decoded : null;
    }
}
