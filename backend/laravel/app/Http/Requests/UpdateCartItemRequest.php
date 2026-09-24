<?php

namespace App\Http\Requests;

use App\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCartItemRequest extends FormRequest
{
    private const ALLOWED = ['quantity'];

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
            'quantity' => ['required', 'integer:strict', 'min:1', 'max:'.CartItem::MAX_QUANTITY],
        ];
    }
}
