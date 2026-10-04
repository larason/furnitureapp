<?php

namespace App\Http\Requests;

use App\Services\Products\UpdateProductInput;
use App\Support\ProductType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProductRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['name', 'slug', 'description', 'product_type', 'price', 'category_id', 'is_active', 'is_published'];

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
            'name' => ['sometimes', 'string', 'min:1', 'max:200'],
            'slug' => ['sometimes', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'product_type' => ['sometimes', Rule::enum(ProductType::class)],
            'price' => ['sometimes', 'required', 'array:amount,currency'],
            'price.amount' => ['required_with:price', 'integer:strict', 'min:0'],
            'price.currency' => ['required_with:price', 'string', 'in:TZS'],
            'category_id' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', $this->strictBoolean()],
            'is_published' => ['sometimes', $this->strictBoolean()],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                    $validator->errors()->add($field, 'This product field is not allowed.');
                }
            }
        });
    }

    public function productInput(): UpdateProductInput
    {
        return new UpdateProductInput($this->validated());
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
