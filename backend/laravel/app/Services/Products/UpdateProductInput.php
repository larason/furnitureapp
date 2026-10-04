<?php

namespace App\Services\Products;

final readonly class UpdateProductInput
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->attributes);
    }
}
