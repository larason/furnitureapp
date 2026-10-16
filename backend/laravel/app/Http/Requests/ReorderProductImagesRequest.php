<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderProductImagesRequest extends FormRequest
{
    private const FIELD = 'images';

    private const MAX_IDENTIFIER_LENGTH = 64;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        $unknown = array_diff(array_keys($input), [self::FIELD]);
        if ($unknown !== []) {
            throw new ApiException(
                ApiErrorCode::INVALID_VALUE,
                'The request field is not supported.',
                422,
                (string) reset($unknown)
            );
        }

        if (! array_key_exists(self::FIELD, $input)) {
            throw new ApiException(
                ApiErrorCode::MISSING_REQUIRED_FIELD,
                'The images field is required.',
                422,
                self::FIELD
            );
        }

        if (is_array($input[self::FIELD])) {
            $this->merge([self::FIELD => array_map(
                fn (mixed $identifier): mixed => is_string($identifier) ? trim($identifier) : $identifier,
                $input[self::FIELD],
            )]);
        }
    }

    /**
     * The submitted list is the complete new order; array length is bounded by
     * the global JSON body limit (413 above it), not by an invented media cap.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            self::FIELD => ['present', 'array'],
            self::FIELD.'.*' => ['string', 'max:'.self::MAX_IDENTIFIER_LENGTH],
        ];
    }

    /** @return list<string> */
    public function imageIdentifiers(): array
    {
        $value = $this->validated()[self::FIELD] ?? [];

        return array_values(array_map(strval(...), $value));
    }
}
