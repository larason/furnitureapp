<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InventoryIndexRequest extends FormRequest
{
    private const ALLOWED = [
        'product', 'variant', 'warehouse_location', 'page', 'per_page',
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
            'product' => ['sometimes', 'string', 'max:100'],
            'variant' => ['sometimes', 'string', 'max:100', 'regex:/^var_[0-9a-z]+$/'],
            'warehouse_location' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
