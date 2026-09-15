<?php

namespace App\Authentication\Clerk;

interface ClerkUserGateway
{
    public function getById(string $clerkUserId): ClerkUserSnapshot;
}
