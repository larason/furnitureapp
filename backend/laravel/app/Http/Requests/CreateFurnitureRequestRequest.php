<?php

namespace App\Http\Requests;

use App\Support\RequestField;
use DomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Phase 10.1 REQ-001 safety boundary.
 *
 * This class deliberately implements only the allow-list, server-controlled
 * field protection, and the minimal structural checks required to prevent
 * unsafe persistence on the internal creation path. Phase 10.2 owns the
 * complete frozen validation matrix (strict unknown-field semantics, full
 * normalization, exhaustive dimension/quantity edge cases) and is expected to
 * extend this class without changing the creation service.
 */
final class CreateFurnitureRequestRequest extends FormRequest
{
    private const ALLOWED_FIELDS = [
        'product_id',
        'quantity',
        'name',
        'phone',
        'email',
        'dimensions',
        'material',
        'color',
        'notes',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'between:1,100'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().-]{6,29}$/'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'dimensions' => ['sometimes', 'nullable', 'array'],
            'material' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['sometimes', 'nullable', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        foreach (['name', 'phone', 'material', 'color', 'notes'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $input[$field] = trim($value);
            }
        }

        $email = $this->input('email');

        if (is_string($email)) {
            $input['email'] = mb_strtolower(trim($email));
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnknownFields($validator);
            $this->assertContactChannel($validator);
            $this->assertDimensions($validator);
        });
    }

    private function rejectUnknownFields(Validator $validator): void
    {
        foreach (array_keys($this->all()) as $field) {
            if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                $validator->errors()->add($field, 'This field is not accepted.');
            }
        }
    }

    private function assertContactChannel(Validator $validator): void
    {
        if ($this->input('phone') === null && $this->input('email') === null) {
            $validator->errors()->add('phone', 'At least one of phone or email is required.');
        }
    }

    private function assertDimensions(Validator $validator): void
    {
        $dimensions = $this->input('dimensions');

        if (! is_array($dimensions) || $dimensions === []) {
            return;
        }

        try {
            RequestField::validateDimensions($dimensions);
        } catch (DomainException $exception) {
            $validator->errors()->add('dimensions', $exception->getMessage());
        }
    }
}
