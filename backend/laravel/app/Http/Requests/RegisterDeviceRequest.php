<?php

namespace App\Http\Requests;

use App\Models\DeviceInstallation;
use App\Support\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an authenticated customer's device registration payload.
 *
 * Ownership is never read from the body. Any user/role/ownership field is
 * rejected outright so a client cannot register a device for someone else.
 */
final class RegisterDeviceRequest extends FormRequest
{
    private const ACCEPTED_FIELDS = [
        'token',
        'platform',
        'app_instance_id',
        'app_version',
        'app_build',
    ];

    private const REJECTED_FIELDS = [
        'user_id',
        'customer_id',
        'role',
        'id',
        'status',
        'owner',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:1', 'max:4096'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'app_instance_id' => ['required', 'string', 'min:1', 'max:'.DeviceInstallation::MAX_APP_INSTANCE_ID],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:'.DeviceInstallation::MAX_APP_VERSION],
            'app_build' => ['sometimes', 'nullable', 'string', 'max:'.DeviceInstallation::MAX_APP_BUILD],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        foreach (['token', 'app_instance_id', 'app_version', 'app_build'] as $field) {
            if (is_string($this->input($field))) {
                $input[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('platform'))) {
            $input['platform'] = strtoupper(trim($this->input('platform')));
        }

        $this->merge($input);
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->all() as $field => $value) {
                if (in_array($field, self::REJECTED_FIELDS, true)) {
                    $validator->errors()->add($field, 'This field cannot be supplied when registering a device.');
                } elseif (! in_array($field, self::ACCEPTED_FIELDS, true)) {
                    $validator->errors()->add($field, 'This field is not accepted when registering a device.');
                }
            }
        });
    }
}
