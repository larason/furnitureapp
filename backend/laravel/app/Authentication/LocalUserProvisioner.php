<?php

namespace App\Authentication;

use App\Authentication\Clerk\ClerkUserGateway;
use App\Authentication\Clerk\ClerkUserSnapshot;
use App\Exceptions\Api\ApiException;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\RoleName;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LocalUserProvisioner
{
    public function __construct(private readonly ClerkUserGateway $gateway) {}

    public function resolve(AuthenticatedClerkIdentity $identity): User
    {
        $existing = User::where('clerk_user_id', $identity->clerkUserId)->first();

        if ($existing !== null) {
            return $existing;
        }

        $snapshot = $this->fetchSnapshot($identity->clerkUserId);

        try {
            return DB::transaction(function () use ($identity, $snapshot): User {
                $mapped = User::where('clerk_user_id', $identity->clerkUserId)->lockForUpdate()->first();

                if ($mapped !== null) {
                    return $mapped;
                }

                if (User::whereNull('clerk_user_id')->where('email', $snapshot->email)->exists()) {
                    throw $this->conflict();
                }

                if ($snapshot->name === null) {
                    throw new ApiException(
                        ApiErrorCode::CONFLICT,
                        'The authenticated identity cannot be provisioned.',
                        409,
                    );
                }

                $user = User::create([
                    'name' => $snapshot->name,
                    'email' => $snapshot->email,
                    'phone' => $snapshot->phone,
                    'email_verified_at' => $snapshot->emailVerified ? now() : null,
                ]);
                $user->forceFill(['clerk_user_id' => $identity->clerkUserId])->save();
                $user->assignRole(RoleName::CUSTOMER->value);
                CustomerProfile::create(['user_id' => $user->id]);

                return $user;
            });
        } catch (ApiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $mapped = User::where('clerk_user_id', $identity->clerkUserId)->first();

            if ($mapped !== null) {
                return $mapped;
            }

            throw new ApiException(
                ApiErrorCode::EXTERNAL_SERVICE_ERROR,
                'The authenticated identity could not be provisioned.',
                503,
                previous: $exception,
            );
        }
    }

    private function fetchSnapshot(string $clerkUserId): ClerkUserSnapshot
    {
        try {
            return $this->gateway->getById($clerkUserId);
        } catch (Throwable $exception) {
            throw new ApiException(
                ApiErrorCode::EXTERNAL_SERVICE_ERROR,
                'The authenticated identity could not be retrieved.',
                503,
                previous: $exception,
            );
        }
    }

    private function conflict(): ApiException
    {
        return new ApiException(
            ApiErrorCode::CONFLICT,
            'The authenticated identity cannot be provisioned.',
            409,
        );
    }
}
