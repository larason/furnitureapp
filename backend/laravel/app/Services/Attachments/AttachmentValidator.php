<?php

namespace App\Services\Attachments;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Http\UploadedFile;

/**
 * Validates an uploaded attachment against the frozen V1 rules: 5 MiB maximum,
 * allow-listed content types, and server-detected content authority backed by
 * `finfo` plus magic-byte signature checks. The client MIME type and filename
 * extension are never trusted.
 */
final class AttachmentValidator
{
    public function validate(UploadedFile $file): ValidatedAttachment
    {
        if (! $file->isValid()) {
            throw $this->invalid();
        }

        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw $this->invalid();
        }

        if ($size > (int) config('attachments.max_bytes')) {
            throw new ApiException(ApiErrorCode::ATTACHMENT_TOO_LARGE, 'The attachment exceeds the 5 MiB limit.', 413, 'attachment');
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw $this->invalid();
        }

        $contentType = $this->detectContentType($path);
        $allowed = (array) config('attachments.allowed_types');

        if ($contentType === null || ! array_key_exists($contentType, $allowed)) {
            throw new ApiException(ApiErrorCode::UNSUPPORTED_ATTACHMENT_TYPE, 'The attachment type is not supported.', 422, 'attachment');
        }

        if (! $this->signatureMatches($path, $contentType)) {
            throw $this->invalid();
        }

        $extension = (string) $allowed[$contentType];

        return new ValidatedAttachment(
            path: $path,
            filename: $this->sanitizeFilename($file->getClientOriginalName(), $extension),
            contentType: $contentType,
            size: $size,
            extension: $extension,
        );
    }

    private function detectContentType(string $path): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->file($path);

        return is_string($type) && $type !== '' ? $type : null;
    }

    private function signatureMatches(string $path, string $contentType): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = (string) fread($handle, 12);
        fclose($handle);

        return match ($contentType) {
            'image/jpeg' => str_starts_with($header, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($header, "\x89PNG\r\n\x1A\n"),
            'image/webp' => str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP',
            'application/pdf' => str_starts_with($header, '%PDF-'),
            default => false,
        };
    }

    private function sanitizeFilename(string $original, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $original));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $name);
        $name = trim($name, ". \t\n\r\0\x0B");

        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = (string) preg_replace('/[^A-Za-z0-9 _.-]+/u', '_', $base);
        $base = trim($base, '._- ');

        if ($base === '') {
            $base = 'attachment';
        }

        $max = (int) config('attachments.max_filename_length');
        $suffix = '.'.$extension;
        $available = max(1, $max - mb_strlen($suffix));

        return mb_substr($base, 0, $available).$suffix;
    }

    private function invalid(): ApiException
    {
        return new ApiException(ApiErrorCode::INVALID_ATTACHMENT, 'The attachment is invalid.', 422, 'attachment');
    }
}
