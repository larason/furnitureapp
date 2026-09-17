<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateMeRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['name', 'phone'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().-]{6,29}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if (is_string($this->input('name'))) {
            $input['name'] = trim($this->input('name'));
        }

        if (is_string($this->input('phone'))) {
            $input['phone'] = trim($this->input('phone'));
        }

        $this->merge($input);
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                    $validator->errors()->add($field, 'This profile field cannot be updated.');
                }
            }

            if ($this->all() === []) {
                $validator->errors()->add('profile', 'At least one profile field is required.');
            }
        });
    }
}
