<?php

namespace App\Authentication\Clerk;

use Throwable;

interface ClerkSessionGateway
{
    /**
     * Revoke a single Clerk session. Other active sessions stay active.
     *
     * @throws Throwable
     */
    public function revoke(string $sessionId): void;
}
