<?php

namespace Tests\Feature;

use App\Models\DeviceInstallation;
use App\Models\User;
use App\Services\Push\PushTokenFingerprint;
use App\Services\Push\StaleDeviceInstallationReaper;
use App\Support\DeviceRegistrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retiring device rows that can no longer be addressed.
 *
 * The reaper must never leave a row active after the provider has rejected its
 * token, and must never touch a row that is still eligible.
 */
final class StaleDeviceInstallationReaperTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unregistered_token_is_deactivated(): void
    {
        $customer = User::factory()->customer()->create();
        $device = DeviceInstallation::factory()->forOwner($customer)->withToken('dead-token')->create();
        $fingerprint = app(PushTokenFingerprint::class)->for('dead-token');

        $reaped = app(StaleDeviceInstallationReaper::class)->reapUnregistered([$fingerprint]);

        $this->assertSame(1, $reaped);
        $this->assertSame(DeviceRegistrationStatus::INACTIVE, $device->refresh()->status);
        $this->assertNotNull($device->deactivated_at);
    }

    public function test_reaping_an_unregistered_token_leaves_other_devices_active(): void
    {
        $customer = User::factory()->customer()->create();
        $dead = DeviceInstallation::factory()->forOwner($customer)->withToken('dead-token')->create();
        $live = DeviceInstallation::factory()->forOwner($customer)->withToken('live-token')->create();

        app(StaleDeviceInstallationReaper::class)->reapUnregistered([
            app(PushTokenFingerprint::class)->for('dead-token'),
        ]);

        $this->assertSame(DeviceRegistrationStatus::INACTIVE, $dead->refresh()->status);
        $this->assertSame(DeviceRegistrationStatus::ACTIVE, $live->refresh()->status);
    }

    public function test_reaping_with_no_fingerprints_changes_nothing(): void
    {
        $customer = User::factory()->customer()->create();
        $device = DeviceInstallation::factory()->forOwner($customer)->create();

        $reaped = app(StaleDeviceInstallationReaper::class)->reapUnregistered([]);

        $this->assertSame(0, $reaped);
        $this->assertSame(DeviceRegistrationStatus::ACTIVE, $device->refresh()->status);
    }

    public function test_reaping_an_already_inactive_row_preserves_its_original_deactivation(): void
    {
        $customer = User::factory()->customer()->create();
        $device = DeviceInstallation::factory()->forOwner($customer)->withToken('dead-token')->create();
        $retiredAt = now()->subYears(3);
        DeviceInstallation::query()->whereKey($device->getKey())->update([
            'status' => DeviceRegistrationStatus::INACTIVE->value,
            'deactivated_at' => $retiredAt,
            'updated_at' => $retiredAt,
        ]);

        $reaped = app(StaleDeviceInstallationReaper::class)->reapUnregistered([
            app(PushTokenFingerprint::class)->for('dead-token'),
        ]);

        // Asserting against a fixed past date, rather than `now()`, is what makes
        // this a real guarantee instead of a same-second accident.
        $this->assertSame(0, $reaped);
        $fresh = $device->refresh();
        $this->assertSame(
            $retiredAt->toDateTimeString(),
            $fresh->deactivated_at?->toDateTimeString(),
        );
        $this->assertSame($retiredAt->toDateTimeString(), $fresh->updated_at->toDateTimeString());
    }

    public function test_a_recently_registered_active_device_is_not_reaped(): void
    {
        config()->set('notifications.push.stale_after_days', 30);
        $customer = User::factory()->customer()->create();
        $device = DeviceInstallation::factory()->forOwner($customer)->create();
        DeviceInstallation::query()->whereKey($device->getKey())
            ->update(['last_registered_at' => now()]);

        $reaped = app(StaleDeviceInstallationReaper::class)->reapStale();

        $this->assertSame(0, $reaped);
        $this->assertSame(DeviceRegistrationStatus::ACTIVE, $device->refresh()->status);
    }

    public function test_a_stale_active_device_is_deactivated(): void
    {
        config()->set('notifications.push.stale_after_days', 30);
        $customer = User::factory()->customer()->create();
        $device = DeviceInstallation::factory()->forOwner($customer)->create();
        DeviceInstallation::query()->whereKey($device->getKey())
            ->update(['last_registered_at' => now()->subDays(45)]);

        $reaped = app(StaleDeviceInstallationReaper::class)->reapStale();

        $this->assertSame(1, $reaped);
        $fresh = $device->refresh();
        $this->assertSame(DeviceRegistrationStatus::INACTIVE, $fresh->status);
        $this->assertNotNull($fresh->deactivated_at);
    }

    public function test_reaping_does_not_reset_the_deactivation_clock(): void
    {
        config()->set('notifications.push.stale_after_days', 30);
        $customer = User::factory()->customer()->create();
        $device = DeviceInstallation::factory()->forOwner($customer)->create();
        DeviceInstallation::query()->whereKey($device->getKey())
            ->update(['last_registered_at' => now()->subDays(45)]);

        app(StaleDeviceInstallationReaper::class)->reapStale();
        $firstRetiredAt = $device->refresh()->deactivated_at;

        // A second pass must leave the original retirement moment intact.
        $reaped = app(StaleDeviceInstallationReaper::class)->reapStale();

        $this->assertSame(0, $reaped);
        $this->assertSame(
            $firstRetiredAt?->toDateTimeString(),
            $device->refresh()->deactivated_at?->toDateTimeString(),
        );
    }

    public function test_a_never_registered_device_ages_out_on_its_creation_time(): void
    {
        config()->set('notifications.push.stale_after_days', 30);
        $customer = User::factory()->customer()->create();
        $device = DeviceInstallation::factory()->forOwner($customer)->create();
        DeviceInstallation::query()->whereKey($device->getKey())->update([
            'last_registered_at' => null,
            'created_at' => now()->subDays(45),
        ]);

        $reaped = app(StaleDeviceInstallationReaper::class)->reapStale();

        $this->assertSame(1, $reaped);
        $this->assertSame(DeviceRegistrationStatus::INACTIVE, $device->refresh()->status);
    }
}
