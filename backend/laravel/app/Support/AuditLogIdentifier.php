<?php

namespace App\Support;

final class AuditLogIdentifier
{
    private const PREFIX = 'audit_';

    public static function encodeId(int $id): string
    {
        return self::PREFIX.base_convert((string) $id, 10, 36);
    }
}
