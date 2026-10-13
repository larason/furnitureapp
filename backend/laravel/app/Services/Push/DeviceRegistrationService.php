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
    /** PostgreSQL reports a unique-key breach with its own SQLSTATE. */
    private const POSTGRES_DUPLICATE_KEY_SQLSTATE = '23505';

    /** MySQL's general integrity-violation state, shared by every constraint class. */
    private const INTEGRITY_VIOLATION_SQLSTATE = '23000';

    /** The MySQL driver code that narrows `23000` down to a duplicate entry. */
    private const MYSQL_DUPLICATE_ENTRY_CODE = 1062;

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

    /**
     * Distinguishes a duplicate-key error from every other integrity failure.
     *
     * PDO reports `errorInfo` as `[SQLSTATE, driverCode, driverMessage]`, so the
     * driver code lives in `errorInfo[1]`, not `errorInfo[0]`. MySQL raises the
     * general `23000` state for foreign keys, NOT NULL and CHECK constraints too,
     * so treating that state alone as a duplicate would swallow a real integrity
     * failure and report it as a contention to retry.
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($sqlState === self::POSTGRES_DUPLICATE_KEY_SQLSTATE) {
            return true;
        }

        return $sqlState === self::INTEGRITY_VIOLATION_SQLSTATE
            && $driverCode === self::MYSQL_DUPLICATE_ENTRY_CODE;
    }
}
