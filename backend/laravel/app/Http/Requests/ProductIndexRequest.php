<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductIndexRequest extends FormRequest
{
    private const ALLOWED = [
        'search', 'category', 'product_type', 'availability', 'min_price', 'max_price',
        'sort', 'sort_direction', 'page', 'per_page',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $unknown = array_diff(array_keys($this->all()), self::ALLOWED);

        if ($unknown !== []) {
            $this->merge(['_unknown_parameter' => reset($unknown)]);
        }
    }

    public function rules(): array
    {
        return [
            '_unknown_parameter' => ['prohibited'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:100'],
            'product_type' => ['sometimes', 'in:IN_STOCK,MADE_TO_ORDER'],
            'availability' => ['sometimes', 'in:available,unavailable'],
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0'],
            'sort' => ['sometimes', 'in:created_at,price,name'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('min_price') && $this->filled('max_price') && $this->integer('min_price') > $this->integer('max_price')) {
                $validator->errors()->add('max_price', 'The max_price must be greater than or equal to min_price.');
            }

        });
    }
}
