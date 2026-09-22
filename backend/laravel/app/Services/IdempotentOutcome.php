<?php

namespace App\Services;

/**
 * Result of a shared idempotent operation execution.
 */
final readonly class IdempotentOutcome
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public array $body,
        public bool $replayed,
    ) {}
}
