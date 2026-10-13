<?php

namespace App\Services\Push;

use App\Models\DeviceInstallation;

/**
 * Result of a device registration, distinguishing a first registration from an
 * update so the API can answer 201 or 200 without re-querying.
 */
final readonly class DeviceRegistration
{
    public function __construct(
        public DeviceInstallation $device,
        public bool $created,
    ) {}
}
