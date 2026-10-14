<?php

namespace App\Support;

use App\Models\DeviceInstallation;

final class DeviceInstallationIdentifier
{
    private const PREFIX = 'dev_';

    public static function encode(DeviceInstallation $device): string
    {
        return self::encodeId((int) $device->getKey());
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
