<?php

namespace App\Services\Fcm;

use RuntimeException;

final class FcmDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $category,
        public readonly bool $transient,
        public readonly bool $tokenInvalid = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
