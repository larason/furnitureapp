<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Models\FurnitureRequest;
use App\Services\Requests\FurnitureRequestOperationalUpdate;
use App\Support\ApiErrorCode;
use App\Support\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFurnitureRequestRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['request_status', 'staff_internal_notes'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function normalizedInput(): FurnitureRequestOperationalUpdate
    {
        $input = $this->all();
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            $field = (string) reset($unknown);

            throw $this->error(ApiErrorCode::INVALID_VALUE, $field, 'The request field is not supported.');
        }

        if ($input === []) {
            throw $this->error(ApiErrorCode::MISSING_REQUIRED_FIELD, null, 'At least one operational field is required.');
        }

        $statusProvided = array_key_exists('request_status', $input);
        $status = $statusProvided ? $this->status($input['request_status']) : null;
        $notesProvided = array_key_exists('staff_internal_notes', $input);
        $notes = $notesProvided ? $this->notes($input['staff_internal_notes']) : null;

        return new FurnitureRequestOperationalUpdate($statusProvided, $status, $notesProvided, $notes);
    }

    private function status(mixed $value): RequestStatus
    {
        if (! is_string($value)) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, 'request_status', 'The request_status field must be a string.');
        }

        $status = RequestStatus::tryFrom($value);

        if ($status === null) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'request_status', 'The request_status field is invalid.');
        }

        return $status;
    }

    private function notes(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, 'staff_internal_notes', 'The staff_internal_notes field must be a string or null.');
        }

        $notes = trim($value);

        if ($notes === '') {
            return null;
        }

        if (mb_strlen($notes) > FurnitureRequest::MAX_STAFF_NOTES) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'staff_internal_notes', 'The staff_internal_notes field is too long.');
        }

        return $notes;
    }

    private function error(ApiErrorCode $code, ?string $field, string $message): ApiException
    {
        return new ApiException($code, $message, 422, $field);
    }
}
