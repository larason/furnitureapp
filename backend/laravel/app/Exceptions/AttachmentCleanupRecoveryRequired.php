<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class AttachmentCleanupRecoveryRequired extends RuntimeException
{
    public function __construct(
        public readonly string $storageDisk,
        public readonly string $storageKey,
        Throwable $previous,
    ) {
        parent::__construct('Attachment cleanup requires persistent recovery.', 0, $previous);
    }
}
