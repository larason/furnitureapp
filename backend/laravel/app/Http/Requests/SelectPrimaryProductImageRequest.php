<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The primary-image action carries its target in the path, so it accepts no
 * body. Rejecting any submitted field keeps a client from attempting to smuggle
 * `is_primary` or `sort_order` through this endpoint.
 */
final class SelectPrimaryProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if ($input !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'This operation does not accept a request body.', 422, (string) array_key_first($input));
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }
}
