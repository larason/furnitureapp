<?php

namespace App\Http\Requests;

use App\Support\ProductType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class OperationalProductIndexRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['search', 'category', 'product_type', 'is_active', 'is_published', 'availability', 'page', 'per_page'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_active', 'is_published'] as $field) {
            if ($this->input($field) === 'true') {
                $this->merge([$field => true]);
            }

            if ($this->input($field) === 'false') {
                $this->merge([$field => false]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:100'],
            'product_type' => ['sometimes', Rule::enum(ProductType::class)],
            'is_active' => ['sometimes', $this->strictBoolean()],
            'is_published' => ['sometimes', $this->strictBoolean()],
            'availability' => ['sometimes', 'in:available,unavailable'],
            'page' => ['sometimes', 'integer:strict', 'min:1'],
            'per_page' => ['sometimes', 'integer:strict', 'min:1', 'max:100'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), self::ALLOWED_FIELDS) as $field) {
                $validator->errors()->add($field, 'This parameter is not allowed.');
            }
        });
    }

    private function strictBoolean(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_bool($value)) {
                $fail("The {$attribute} field must be a boolean.");
            }
        };
    }
}
