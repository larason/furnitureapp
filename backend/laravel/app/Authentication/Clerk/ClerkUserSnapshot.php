<?php

namespace App\Authentication\Clerk;

final readonly class ClerkUserSnapshot
{
    public function __construct(
        public string $clerkUserId,
        public string $email,
        public ?string $name,
        public ?string $phone,
        public bool $emailVerified,
    ) {}
}
