<?php

namespace App\Services\ProductImages;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ProductImageStorage
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function put(string $key, ValidatedProductImage $image): void
    {
        $stream = fopen($image->path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The validated image could not be read.');
        }

        try {
            if ($this->disk()->writeStream($key, $stream, ['ContentType' => $image->contentType, 'CacheControl' => self::CACHE_CONTROL]) === false) {
                throw new RuntimeException('The product image could not be stored.');
            }
        } finally {
            fclose($stream);
        }
    }

    public function delete(string $key): void
    {
        if ($this->disk()->delete($key) === false) {
            throw new RuntimeException('The product image could not be deleted.');
        }
    }

    public function publicUrl(string $key): string
    {
        $baseUrl = rtrim(trim((string) config('product_images.public_base_url')), '/');
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('Product image public delivery is not configured.');
        }

        return $baseUrl.'/'.ltrim($key, '/');
    }

    public function validatePublicBaseUrl(): void
    {
        $this->publicUrl('');
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('product_images.disk'));
    }
}
