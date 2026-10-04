<?php

namespace App\Services\ProductImages;

final readonly class ValidatedProductImage
{
    public function __construct(
        public string $path,
        public string $contentType,
        public string $extension,
    ) {}
}
