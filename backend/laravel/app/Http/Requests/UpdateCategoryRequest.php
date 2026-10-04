<?php

namespace App\Http\Requests;

use App\Services\Categories\UpdateCategoryInput;

final class UpdateCategoryRequest extends CategoryFormRequest
{
    public function rules(): array
    {
        return $this->categoryRules(false);
    }

    public function categoryInput(): UpdateCategoryInput
    {
        $validated = $this->validated();

        return new UpdateCategoryInput(
            hasName: array_key_exists('name', $validated),
            name: $validated['name'] ?? null,
            hasSlug: array_key_exists('slug', $validated),
            slug: $validated['slug'] ?? null,
            hasDescription: array_key_exists('description', $validated),
            description: $validated['description'] ?? null,
            hasImageUrl: array_key_exists('image', $validated),
            imageUrl: $validated['image'] ?? null,
        );
    }
}
