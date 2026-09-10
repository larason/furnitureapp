<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guest-cart bearer credential handling (phases/group-C-phases.md §3.8.2-3.8.4).
 *
 * The raw guest_token is a high-entropy server-generated credential, never
 * persisted and never logged. The database stores only a keyed HMAC-SHA-256
 * digest (guest_token_digest) derived from the raw token with the server-managed
 * secret configured via the GUEST_CART_TOKEN_KEY key.
 */
class GuestCartCredential
{
    public static function generate(): string
    {
        return Str::random(48);
    }

    public static function digest(string $rawToken): string
    {
        $secret = (string) config('cart.guest_token_key');

        if ($secret === '') {
            throw new RuntimeException('GUEST_CART_TOKEN_KEY is not configured; guest-cart digests require a server-managed secret.');
        }

        return hash_hmac('sha256', $rawToken, $secret);
    }
}
