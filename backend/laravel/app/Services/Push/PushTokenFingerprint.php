<?php

namespace App\Services\Push;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;

/**
 * Irreversible, keyed fingerprint of an FCM registration token.
 *
 * One active owner per token is a database constraint on this value. A plain
 * hash of a bearer-like token would be vulnerable to offline guessing, so the
 * digest is keyed with a dedicated secret and never leaves the server.
 *
 * The fingerprint must be deterministic for the lifetime of the key. Rotating
 * the key requires every stored device row to be re-registered.
 */
final class PushTokenFingerprint
{
    private const ALGORITHM = 'sha256';

    public function for(string $token): string
    {
        return hash_hmac(self::ALGORITHM, $token, $this->key());
    }

    public function matches(string $token, string $fingerprint): bool
    {
        return hash_equals($fingerprint, $this->for($token));
    }

    private function key(): string
    {
        $key = config('notifications.push.token_fingerprint_key');
        if (! is_string($key) || trim($key) === '') {
            $key = (string) config('app.key');
        }

        if (trim($key) === '') {
            throw new ApiException(
                ApiErrorCode::INTERNAL_SERVER_ERROR,
                'Device registration is not configured correctly.',
                500,
            );
        }

        return $key;
    }
}
