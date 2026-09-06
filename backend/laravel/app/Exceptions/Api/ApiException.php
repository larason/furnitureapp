<?php

namespace App\Exceptions\Api;

use App\Support\ApiErrorCode;
use Exception;
use Throwable;

/**
 * Base exception for deliberately raised API failures.
 *
 * Carries the frozen machine code, HTTP status, optional field and safe
 * details. Domain phases raise this (or subclasses) with an already-decided
 * failure; the renderer only maps it to the contract envelope.
 */
class ApiException extends Exception
{
    public function __construct(
        private readonly ApiErrorCode $errorCode,
        string $message,
        private readonly int $status = 422,
        private readonly ?string $field = null,
        private readonly array $details = [],
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): ApiErrorCode
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function details(): array
    {
        return $this->details;
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
