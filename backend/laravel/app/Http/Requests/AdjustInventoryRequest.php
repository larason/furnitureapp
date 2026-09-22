<?php

namespace App\Http\Requests;

use App\Support\InventoryAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AdjustInventoryRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['quantity_delta', 'reason'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_delta' => ['required', 'integer:strict', 'not_in:0'],
            'reason' => ['required', Rule::enum(InventoryAdjustmentReason::class)],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                    $validator->errors()->add($field, 'This inventory field cannot be adjusted.');
                }
            }
        });
    }
}
