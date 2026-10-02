<?php

namespace App\Services\Attachments;

/**
 * Trusted, server-derived attachment metadata. Downstream storage never
 * re-reads the client MIME type or original filename.
 */
final readonly class ValidatedAttachment
{
    public function __construct(
        public string $path,
        public string $filename,
        public string $contentType,
        public int $size,
        public string $extension,
    ) {}
}
