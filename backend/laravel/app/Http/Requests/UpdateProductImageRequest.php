<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductImageRequest extends FormRequest
{
    private const FIELD = 'alt_text';

    private const MAX_ALT_TEXT_LENGTH = 500;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        $unknown = array_diff(array_keys($input), [self::FIELD]);
        if ($unknown !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The request field is not supported.', 422, (string) reset($unknown));
        }

        if (! array_key_exists(self::FIELD, $input)) {
            throw new ApiException(ApiErrorCode::MISSING_REQUIRED_FIELD, 'The alt_text field is required.', 422, self::FIELD);
        }

        if (is_string($input[self::FIELD])) {
            $this->merge([self::FIELD => trim($input[self::FIELD])]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            self::FIELD => ['present', 'nullable', 'string', 'max:'.self::MAX_ALT_TEXT_LENGTH],
        ];
    }

    /** An absent-value clearing request and a blank string are both stored as null. */
    public function altText(): ?string
    {
        $value = $this->validated()[self::FIELD] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
