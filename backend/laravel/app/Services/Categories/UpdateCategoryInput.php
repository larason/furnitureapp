<?php

namespace App\Services\Categories;

final readonly class UpdateCategoryInput
{
    public function __construct(
        public bool $hasName,
        public ?string $name,
        public bool $hasSlug,
        public ?string $slug,
        public bool $hasDescription,
        public ?string $description,
        public bool $hasImageUrl,
        public ?string $imageUrl,
    ) {}
}
