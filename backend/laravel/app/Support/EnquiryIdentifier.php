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
        return Base36Identifier::encode(self::PREFIX, $id);
    }

    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
