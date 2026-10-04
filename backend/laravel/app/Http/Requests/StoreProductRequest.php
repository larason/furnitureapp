<?php

namespace App\Http\Requests;

use App\Services\Products\CreateProductInput;
use App\Support\ProductType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:200'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'price' => ['required', 'array:amount,currency'],
            'price.amount' => ['required', 'integer:strict', 'min:0'],
            'price.currency' => ['required', 'string', 'in:TZS'],
            'category_id' => ['required', 'string', 'max:100'],
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

    public function productInput(): CreateProductInput
    {
        $validated = $this->validated();

        return new CreateProductInput(
            name: $validated['name'],
            slug: $validated['slug'],
            description: $validated['description'] ?? null,
            productType: ProductType::from($validated['product_type']),
            priceAmount: $validated['price']['amount'],
            priceCurrency: $validated['price']['currency'],
            categoryIdentifier: $validated['category_id'],
            isActive: $validated['is_active'] ?? true,
            isPublished: $validated['is_published'] ?? true,
        );
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
