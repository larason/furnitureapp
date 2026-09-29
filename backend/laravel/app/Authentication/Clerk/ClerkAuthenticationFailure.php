<?php

namespace App\Authentication\Clerk;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Throwable;

final class ClerkAuthenticationFailure extends ApiException
{
    public static function missing(): self
    {
        return new self(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
    }

    public static function expired(): self
    {
        return new self(ApiErrorCode::SESSION_EXPIRED, 'The authentication session has expired.', 401);
    }

    public static function pending(): self
    {
        return new self(ApiErrorCode::SESSION_EXPIRED, 'The authentication session must complete required security tasks.', 401);
    }

    public static function invalid(): self
    {
        return new self(ApiErrorCode::INVALID_AUTHENTICATION, 'The authentication credential is invalid.', 401);
    }

    public static function external(?Throwable $previous = null): self
    {
        return new self(ApiErrorCode::EXTERNAL_SERVICE_ERROR, 'The authentication provider is temporarily unavailable.', 503, previous: $previous);
    }

    public static function internal(): self
    {
        return new self(ApiErrorCode::INTERNAL_SERVER_ERROR, 'The authentication service is not configured correctly.', 500);
    }
}
