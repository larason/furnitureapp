<?php

namespace App\Services\Push;

use App\Exceptions\Api\ApiException;
use App\Models\DeviceInstallation;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\DeviceRegistrationStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent registration and rotation of a customer app installation.
 *
 * Ownership is decided only by the authenticated customer passed in. A token
 * that another customer *currently* holds is refused rather than transferred,
 * because a client-supplied token or installation id is not proof of identity.
 *
 * A token retired by a previous owner is history, not a claim. It keeps the row
 * and the original `user_id` so the record of who held the device survives, but
 * it does not block the device being registered for whoever holds it now. The
 * database enforces the same rule through an ACTIVE-only unique index, so a
 * concurrent race resolves to the same outcome as a sequential one: at most one
 * ACTIVE owner per token, decided by the database rather than by a read.
 */
final class DeviceRegistrationService
{
    public function __construct(
        private readonly PushTokenCipher $cipher,
        private readonly PushTokenFingerprint $fingerprint,
    ) {}

    /**
     * Registers or rotates the installation for a customer.
     *
     * @param  array{app_instance_id: string, token: string, platform: string, app_version?: string|null, app_build?: string|null}  $attributes
     */
    public function register(User $customer, array $attributes): DeviceRegistration
    {
        $fingerprint = $this->fingerprint->for($attributes['token']);
        $ciphertext = $this->cipher->encrypt($attributes['token']);

        try {
            return DB::transaction(fn (): DeviceRegistration => $this->persist($customer, $attributes, $fingerprint, $ciphertext));
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            // A concurrent request won the race. Re-resolve against the committed
            // row so the loser returns the same result instead of a spurious error.
            return $this->resolveConcurrentRegistration($customer, $attributes, $fingerprint);
        }
    }

    /**
     * @param  array{app_instance_id: string, token: string, platform: string, app_version?: string|null, app_build?: string|null}  $attributes
     */
    private function persist(User $customer, array $attributes, string $fingerprint, string $ciphertext): DeviceRegistration
    {
        $this->assertTokenIsUnclaimed($customer, $fingerprint);

        $device = $customer->deviceInstallations()
            ->where('app_instance_id', $attributes['app_instance_id'])
            ->first();

        $values = [
            'token_ciphertext' => $ciphertext,
            'token_fingerprint' => $fingerprint,
            'app_version' => $attributes['app_version'] ?? null,
            'app_build' => $attributes['app_build'] ?? null,
            'last_registered_at' => Carbon::now(),
        ];

        if ($device === null) {
            $device = $customer->deviceInstallations()->create([
                'platform' => $attributes['platform'],
                'app_instance_id' => $attributes['app_instance_id'],
                'status' => DeviceRegistrationStatus::ACTIVE,
                'deactivated_at' => null,
                ...$values,
            ]);

            return new DeviceRegistration($device, true);
        }

        $device->fill($values);
        $device->status = DeviceRegistrationStatus::ACTIVE;
        $device->deactivated_at = null;
        $device->save();

        return new DeviceRegistration($device->refresh(), false);
    }

    /**
     * @param  array{app_instance_id: string, token: string, platform: string, app_version?: string|null, app_build?: string|null}  $attributes
     */
    private function resolveConcurrentRegistration(User $customer, array $attributes, string $fingerprint): DeviceRegistration
    {
        $this->assertTokenIsUnclaimed($customer, $fingerprint);

        $device = $customer->deviceInstallations()
            ->where('app_instance_id', $attributes['app_instance_id'])
            ->first();

        if ($device === null) {
            throw new ApiException(
                ApiErrorCode::CONFLICT,
                'The device registration conflicted with a concurrent request. Retry the request.',
                409,
            );
        }

        $device->fill([
            'token_ciphertext' => $this->cipher->encrypt($attributes['token']),
            'token_fingerprint' => $fingerprint,
            'app_version' => $attributes['app_version'] ?? null,
            'app_build' => $attributes['app_build'] ?? null,
            'last_registered_at' => Carbon::now(),
        ]);
        $device->status = DeviceRegistrationStatus::ACTIVE;
        $device->deactivated_at = null;

        try {
            $device->save();
        } catch (QueryException $exception) {
            // Losing a second race must surface as the frozen conflict code, not
            // as an unhandled database error.
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            throw $this->tokenConflict();
        }

        return new DeviceRegistration($device->refresh(), false);
    }

    /**
     * Refuses a token that another customer currently holds.
     *
     * Only ACTIVE rows count. A retired registration is history, not a claim: it
     * keeps the record of which customer once held the device, but it must not
     * stop the device being registered for whoever holds it now. That is what
     * makes handing a device to a new customer possible.
     *
     * The database enforces the same rule through an ACTIVE-only unique index,
     * so a concurrent request cannot produce two ACTIVE owners. A customer whose
     * own retired row matches is deliberately not treated as a conflict here;
     * {@see persist} decides whether to reactivate that row or open a new one.
     */
    private function assertTokenIsUnclaimed(User $customer, string $fingerprint): void
    {
        $heldByAnother = DeviceInstallation::query()
            ->where('token_fingerprint', $fingerprint)
            ->where('status', DeviceRegistrationStatus::ACTIVE->value)
            ->where('user_id', '!=', $customer->getKey())
            ->exists();

        if ($heldByAnother) {
            throw $this->tokenConflict();
        }
    }

    private function tokenConflict(): ApiException
    {
        return new ApiException(
            ApiErrorCode::CONFLICT,
            'This device is already registered to another account. Sign out of that account first.',
            409,
        );
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || (int) ($exception->errorInfo[0] ?? 0) === 1062;
    }
}
