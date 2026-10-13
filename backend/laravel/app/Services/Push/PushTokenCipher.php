<?php

namespace App\Services\Push;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Protects a recoverable FCM registration token at rest.
 *
 * Delivery later needs the raw token, so it cannot be reduced to a one-way
 * digest. The token is therefore encrypted with the application key and never
 * persisted, logged, or serialized in plaintext. Uniqueness is enforced by
 * {@see PushTokenFingerprint}, which is deliberately irreversible.
 */
final class PushTokenCipher
{
    public function encrypt(string $token): string
    {
        try {
            return Crypt::encryptString($token);
        } catch (Throwable $exception) {
            throw new ApiException(
                ApiErrorCode::INTERNAL_SERVER_ERROR,
                'The device registration could not be stored.',
                500,
                previous: $exception,
            );
        }
    }

    public function decrypt(string $ciphertext): string
    {
        try {
            return Crypt::decryptString($ciphertext);
        } catch (Throwable $exception) {
            throw new ApiException(
                ApiErrorCode::INTERNAL_SERVER_ERROR,
                'The stored device registration could not be read.',
                500,
                previous: $exception,
            );
        }
    }
}
