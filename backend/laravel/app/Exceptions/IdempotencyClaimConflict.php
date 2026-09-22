<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Internal signal: the durable idempotency claim hit its unique constraint,
 * meaning another request already owns the same scoped key. Never returned to
 * clients directly.
 */
final class IdempotencyClaimConflict extends RuntimeException {}
