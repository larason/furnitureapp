<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class EnsureActiveAccount
{
    private const ACTIVE_ACCOUNT_STATE = 'ACTIVE';

    public static function assert(User $user): void
    {
        if ($user->account_state !== null && $user->account_state !== self::ACTIVE_ACCOUNT_STATE) {
            throw new AuthorizationException('The authenticated account is not active.');
        }
    }
}
