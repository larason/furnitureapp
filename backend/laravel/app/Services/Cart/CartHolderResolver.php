<?php

namespace App\Services\Cart;

use App\Models\User;
use App\Support\GuestCartCredential;
use Illuminate\Http\Request;

/**
 * Resolves the holder context (authenticated customer or guest) for a cart
 * request. Authenticated identity always wins; no implicit merge occurs here.
 */
final class CartHolderResolver
{
    public function __construct(private readonly GuestCartTransport $transport) {}

    public function resolve(Request $request): CartHolder
    {
        $user = $request->user();

        if ($user instanceof User) {
            return CartHolder::customer($user);
        }

        $credential = $this->transport->readCredential($request);

        if ($credential === null) {
            $raw = GuestCartCredential::generate();

            return CartHolder::guest($raw, GuestCartCredential::digest($raw), false);
        }

        return CartHolder::guest($credential, GuestCartCredential::digest($credential), true);
    }
}
