<?php

namespace App\Authentication\Clerk;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Throwable;

final readonly class RevokeClerkSession
{
    public function __construct(private ClerkSessionGateway $gateway) {}

    public function revoke(string $sessionId): void
    {
        try {
            $this->gateway->revoke($sessionId);
        } catch (Throwable $exception) {
            throw new ApiException(
                ApiErrorCode::EXTERNAL_SERVICE_ERROR,
                'The authentication session could not be revoked.',
                503,
                previous: $exception,
            );
        }
    }
}
