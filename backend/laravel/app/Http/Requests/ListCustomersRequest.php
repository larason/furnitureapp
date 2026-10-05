<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreatesApiValidationError;
use App\Http\Requests\Concerns\ParsesPositiveQueryInteger;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

final class ListCustomersRequest extends FormRequest
{
    use CreatesApiValidationError;
    use ParsesPositiveQueryInteger;

    private const ALLOWED_FIELDS = ['page', 'per_page'];

    private const DEFAULT_PER_PAGE = 20;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /** @return array{page: int, per_page: int} */
    public function pagination(): array
    {
        $input = $this->query();
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, (string) reset($unknown), 'The query parameter is not supported.');
        }

        return [
            'page' => $this->optionalPositiveQueryInteger($input, 'page', 1),
            'per_page' => $this->optionalPositiveQueryInteger($input, 'per_page', self::DEFAULT_PER_PAGE, 100),
        ];
    }
}
