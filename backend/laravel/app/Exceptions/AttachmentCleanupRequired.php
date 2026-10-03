<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class AttachmentCleanupRequired extends RuntimeException
{
    public function __construct(
        public readonly string $storageDisk,
        public readonly string $storageKey,
        Throwable $metadataFailure,
        public readonly Throwable $cleanupFailure,
    ) {
        parent::__construct('Attachment cleanup must be retried.', 0, $metadataFailure);
    }
}
