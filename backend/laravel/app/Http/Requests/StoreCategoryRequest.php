<?php

namespace App\Http\Requests;

use App\Services\Categories\CreateCategoryInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:255'],
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
