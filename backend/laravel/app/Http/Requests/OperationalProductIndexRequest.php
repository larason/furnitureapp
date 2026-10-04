<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class OperationalProductIndexRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['page', 'per_page'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
}
