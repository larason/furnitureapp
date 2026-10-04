<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A private attachment disk read/write/delete failure.
 *
 * Dedicated to the storage boundary so callers can distinguish it from
 * generic runtime failures while still catching it as a `RuntimeException`.
 */
final class AttachmentStorageException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('Unable to read the uploaded attachment.');
    }

    public static function notStored(?Throwable $previous = null): self
    {
        return new self('Unable to store the uploaded attachment.', 0, $previous);
    }

    public static function cleanupFailed(): self
    {
        return new self('Attachment cleanup failed.');
    }
}
