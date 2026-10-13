<?php

namespace App\Services\Push;

use App\Models\DeviceInstallation;
use App\Support\DeviceRegistrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Retires device rows that can no longer receive a notification.
 *
 * Two categories are reaped, and neither touches a row that is still eligible:
 *
 * 1. Rows the provider has rejected as `UNREGISTERED`, recorded by the delivery
 *    attempt. An unregistered token can never be delivered to again.
 * 2. ACTIVE rows that have not re-registered within the stale window, so an
 *    abandoned installation is retired instead of lingering indefinitely.
 *
 * Rows are deactivated rather than deleted, preserving the audit trail of which
 * customer once held the installation. Only the first deactivation writes
 * timestamps, so a retired row keeps the moment it was actually retired.
 */
final class StaleDeviceInstallationReaper
{
    public function reapUnregistered(array $fingerprints): int
    {
        $fingerprints = array_values(array_unique(array_filter(
            $fingerprints,
            fn (mixed $fingerprint): bool => is_string($fingerprint) && $fingerprint !== '',
        )));

        if ($fingerprints === []) {
            return 0;
        }

        return $this->deactivate(
            DeviceInstallation::query()->whereIn('token_fingerprint', $fingerprints)
        );
    }

    public function reapStale(): int
    {
        $days = max(1, (int) config('notifications.push.stale_after_days', 90));
        $cutoff = Carbon::now()->subDays($days);

        // A device is stale when it has not re-registered within the window. The
        // registration timestamp is the liveness signal; `created_at` covers a
        // row that never recorded one.
        return $this->deactivate(
            DeviceInstallation::query()
                ->where(fn (Builder $query): Builder => $query
                    ->where('last_registered_at', '<', $cutoff)
                    ->orWhere(fn (Builder $inner): Builder => $inner
                        ->whereNull('last_registered_at')
                        ->where('created_at', '<', $cutoff)))
        );
    }

    /** @param Builder<DeviceInstallation> $query */
    private function deactivate(Builder $query): int
    {
        // Only an ACTIVE row is transitioned. Restricting the write to the first
        // deactivation keeps the original `deactivated_at` of an already INACTIVE
        // row, so the audit trail records when the device was actually retired
        // and reaping stays idempotent. It also stops a reaped row from having
        // its staleness clock silently reset by a later pass.
        return $query
            ->where('status', DeviceRegistrationStatus::ACTIVE->value)
            ->update([
                'status' => DeviceRegistrationStatus::INACTIVE->value,
                'deactivated_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }
}
