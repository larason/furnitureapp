<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Http\Middleware\EnforceApiRequestLimits;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function read(): bool
    {
        $query = $this->query();
        if ($query !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'Query parameters are not supported.', 422, (string) array_key_first($query));
        }

        $content = $this->attributes->get(EnforceApiRequestLimits::JSON_BODY_ATTRIBUTE);
        if ($content !== null && $content !== '' && ! is_object(json_decode((string) $content))) {
            throw new ApiException(ApiErrorCode::INVALID_TYPE, 'The request body must be a JSON object.', 422);
        }

        $input = $this->request->all();
        $unknown = array_diff(array_keys($input), ['read']);
        if ($unknown !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The request field is not supported.', 422, (string) reset($unknown));
        }

        if (! array_key_exists('read', $input) || ! is_bool($input['read'])) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The read field must be boolean.', 422, 'read');
        }

        return $input['read'];
    }
}
