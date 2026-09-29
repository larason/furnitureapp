<?php

namespace App\Services;

/**
 * Result of a shared idempotent operation execution. `status` preserves the
 * original success HTTP status so a replay (e.g. Checkout 201) is faithful.
 */
final readonly class IdempotentOutcome
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public array $body,
        public bool $replayed,
        public int $status = 200,
    ) {}
}
