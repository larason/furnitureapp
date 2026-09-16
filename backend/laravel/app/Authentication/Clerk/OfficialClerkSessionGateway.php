<?php

namespace App\Authentication\Clerk;

use Clerk\Backend\ClerkBackend;
use Clerk\Backend\Models\Components\Status;
use RuntimeException;
use Throwable;

final class OfficialClerkSessionGateway implements ClerkSessionGateway
{
    public function __construct(private readonly ClerkBackend $client) {}

    public function revoke(string $sessionId): void
    {
        try {
            $response = $this->client->sessions->revoke($sessionId);
            $session = $response->session;
        } catch (Throwable $exception) {
            throw new RuntimeException('Clerk session revocation failed.', 0, $exception);
        }

        if ($session === null || $session->id !== $sessionId || $session->status !== Status::Revoked) {
            throw new RuntimeException('Clerk session revocation returned no matching revoked session.');
        }
    }
}
