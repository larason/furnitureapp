<?php

namespace App\Authentication;

final readonly class AuthenticatedClerkIdentity
{
    public function __construct(
        public string $clerkUserId,
        public ?string $sessionId,
        public ?string $issuer,
    ) {}
}
