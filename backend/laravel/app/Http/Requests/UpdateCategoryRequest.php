<?php

namespace App\Http\Requests;

use App\Services\Categories\UpdateCategoryInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateCategoryRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['name', 'slug', 'description', 'image'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'slug'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'slug' => ['sometimes', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'image' => ['sometimes', 'nullable', 'string', 'url', 'max:255'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                    $validator->errors()->add($field, 'This category field is not allowed.');
                }
            }
        });
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
