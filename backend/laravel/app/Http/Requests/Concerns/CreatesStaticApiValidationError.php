<?php

namespace App\Http\Requests\Concerns;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;

trait CreatesStaticApiValidationError
{
    protected static function error(ApiErrorCode $code, string $field, string $message): ApiException
    {
        return new ApiException($code, $message, 422, $field);
    }
}
