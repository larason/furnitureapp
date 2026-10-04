<?php

namespace App\Http\Requests;

use App\Services\Categories\CreateCategoryInput;

final class StoreCategoryRequest extends CategoryFormRequest
{
    public function rules(): array
    {
        return $this->categoryRules(true);
    }

    public function categoryInput(): CreateCategoryInput
    {
        $validated = $this->validated();

        return new CreateCategoryInput(
            name: $validated['name'],
            slug: $validated['slug'],
            description: $validated['description'] ?? null,
            imageUrl: $validated['image'] ?? null,
        );
    }
}
