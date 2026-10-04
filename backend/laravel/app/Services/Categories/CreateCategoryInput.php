<?php

namespace App\Services\Categories;

final readonly class CreateCategoryInput
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $imageUrl,
    ) {}
}
