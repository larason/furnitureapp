<?php

namespace App\Services\ProductImages;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Http\UploadedFile;

final class ProductImageValidator
{
    public function validate(UploadedFile $file): ValidatedProductImage
    {
        if (! $file->isValid() || (int) $file->getSize() <= 0) {
            throw $this->invalid();
        }

        if ((int) $file->getSize() > (int) config('product_images.max_bytes')) {
            throw new ApiException(ApiErrorCode::REQUEST_TOO_LARGE, 'The image exceeds the configured upload limit.', 413, 'image');
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw $this->invalid();
        }

        $contentType = $this->detectedContentType($path);
        $allowedTypes = (array) config('product_images.allowed_types');
        if ($contentType === null || ! array_key_exists($contentType, $allowedTypes) || ! $this->isImage($path, $contentType)) {
            throw $this->invalid();
        }

        $clientContentType = $file->getClientMimeType();
        if (array_key_exists($clientContentType, $allowedTypes) && $clientContentType !== $contentType) {
            throw $this->invalid();
        }

        if ($contentType === 'image/jpeg' && $this->hasGpsMetadata($path)) {
            throw $this->invalid();
        }

        return new ValidatedProductImage($path, $contentType, (string) $allowedTypes[$contentType]);
    }

    private function detectedContentType(string $path): ?string
    {
        $contentType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($contentType) && $contentType !== '' ? $contentType : null;
    }

    private function isImage(string $path, string $contentType): bool
    {
        $header = file_get_contents($path, false, null, 0, 12);
        $image = @getimagesize($path);
        if (! is_string($header) || $image === false || $image['mime'] !== $contentType) {
            return false;
        }

        return match ($contentType) {
            'image/jpeg' => str_starts_with($header, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($header, "\x89PNG\r\n\x1A\n"),
            'image/webp' => str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP',
            default => false,
        };
    }

    private function hasGpsMetadata(string $path): bool
    {
        if (! function_exists('exif_read_data')) {
            throw $this->invalid();
        }

        $metadata = @exif_read_data($path, null, true, false);

        return is_array($metadata) && isset($metadata['GPS']);
    }

    private function invalid(): ApiException
    {
        return new ApiException(ApiErrorCode::INVALID_VALUE, 'The image must be a valid JPEG, PNG, or WebP file without location metadata.', 422, 'image');
    }
}
