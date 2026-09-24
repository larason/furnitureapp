<?php

namespace App\Services\Cart;

use App\Models\User;

final readonly class CartHolder
{
    private function __construct(
        public ?User $user,
        public ?string $digest,
        public ?string $rawToken,
        public bool $credentialSupplied,
    ) {}

    public static function customer(User $user): self
    {
        return new self($user, null, null, false);
    }

    public static function guest(string $rawToken, string $digest, bool $credentialSupplied): self
    {
        return new self(null, $digest, $rawToken, $credentialSupplied);
    }
}
