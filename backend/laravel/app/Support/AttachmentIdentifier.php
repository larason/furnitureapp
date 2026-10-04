<?php

namespace App\Support;

use App\Models\Attachment;

final class AttachmentIdentifier
{
    private const PREFIX = 'att_';

    public static function encode(Attachment $attachment): string
    {
        return self::encodeId((int) $attachment->getKey());
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
