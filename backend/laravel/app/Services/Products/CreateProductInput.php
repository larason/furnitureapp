<?php

namespace App\Services\Products;

use App\Support\ProductType;

final readonly class CreateProductInput
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public ProductType $productType,
        public int $priceAmount,
        public string $priceCurrency,
        public string $categoryIdentifier,
        public bool $isActive,
        public bool $isPublished,
    ) {}
}
