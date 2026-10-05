<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Models\Enquiry;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

final class CloseEnquiryRequest extends FormRequest
{
    private const STAFF_NOTES_FIELD = 'staff_internal_notes';

    private const ALLOWED_FIELDS = [self::STAFF_NOTES_FIELD];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function hasStaffInternalNotes(): bool
    {
        return array_key_exists(self::STAFF_NOTES_FIELD, $this->bodyInput());
    }

    public function staffInternalNotes(): ?string
    {
        $input = $this->bodyInput();

        if (! $this->hasStaffInternalNotes()) {
            return null;
        }

        $notes = $input[self::STAFF_NOTES_FIELD];
        if ($notes === null) {
            return null;
        }

        if (! is_string($notes)) {
            throw new ApiException(ApiErrorCode::INVALID_TYPE, 'The staff_internal_notes field must be a string or null.', 422, self::STAFF_NOTES_FIELD);
        }

        $notes = trim($notes);
        if ($notes === '') {
            return null;
        }

        if (mb_strlen($notes) > Enquiry::MAX_STAFF_NOTES) {
            throw $this->error(self::STAFF_NOTES_FIELD, 'The staff_internal_notes field is too long.');
        }

        return $notes;
    }

    /** @return array<string, mixed> */
    private function bodyInput(): array
    {
        $query = $this->query();
        if ($query !== []) {
            throw $this->error((string) array_key_first($query), 'Query parameters are not supported.');
        }

        $input = array_replace($this->request->all(), $this->allFiles());
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);
        if ($unknown !== []) {
            throw $this->error((string) reset($unknown), 'The request field is not supported.');
        }

        return $input;
    }

    private function error(string $field, string $message): ApiException
    {
        return new ApiException(ApiErrorCode::INVALID_VALUE, $message, 422, $field);
    }
}
