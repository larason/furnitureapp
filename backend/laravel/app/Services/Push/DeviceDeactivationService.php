<?php

namespace App\Services\Push;

use App\Models\DeviceInstallation;
use App\Models\User;
use App\Support\DeviceRegistrationStatus;
use Illuminate\Support\Carbon;

/**
 * Deactivation of a single customer device installation.
 *
 * Ownership is object-level: the caller must own the row, and a row owned by
 * anyone else is reported as absent so the API cannot be used to probe for
 * other customers' devices.
 */
final class DeviceDeactivationService
{
    public function deactivate(User $customer, int $deviceId): ?DeviceInstallation
    {
        $device = DeviceInstallation::query()
            ->whereKey($deviceId)
            ->where('user_id', $customer->getKey())
            ->first();

        if ($device === null) {
            return null;
        }

        if (! $device->isActive()) {
            return $device;
        }

        $device->status = DeviceRegistrationStatus::INACTIVE;
        $device->deactivated_at = Carbon::now();
        $device->save();

        return $device;
    }

    /**
     * Deactivates every installation for a customer, used on sign-out.
     */
    public function deactivateAllFor(User $customer): int
    {
        return DeviceInstallation::query()
            ->where('user_id', $customer->getKey())
            ->where('status', DeviceRegistrationStatus::ACTIVE->value)
            ->update([
                'status' => DeviceRegistrationStatus::INACTIVE->value,
                'deactivated_at' => Carbon::now(),
            ]);
    }
}
