<?php

namespace App\Http\Requests;

use App\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;

final class AddCartItemRequest extends FormRequest
{
    private const ALLOWED = ['product_id', 'variant_id', 'quantity'];

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
            'product_id' => ['required', 'string', 'regex:/^prod_[0-9a-z]+$/'],
            'variant_id' => ['sometimes', 'nullable', 'string', 'regex:/^var_[0-9a-z]+$/'],
            'quantity' => ['required', 'integer:strict', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ];
    }
}
