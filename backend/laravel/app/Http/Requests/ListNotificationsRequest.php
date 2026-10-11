<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreatesApiValidationError;
use App\Http\Requests\Concerns\ParsesPositiveQueryInteger;
use App\Support\ApiErrorCode;
use App\Support\NotificationType;
use Illuminate\Foundation\Http\FormRequest;

final class ListNotificationsRequest extends FormRequest
{
    use CreatesApiValidationError;
    use ParsesPositiveQueryInteger;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /** @return array{page: int, per_page: int, unread: bool|null, type: NotificationType|null} */
    public function filters(): array
    {
        $unknown = array_diff(array_keys($this->query()), ['page', 'per_page', 'unread', 'type']);
        if ($unknown !== []) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, (string) reset($unknown), 'The query parameter is not supported.');
        }

        return [
            'page' => $this->positiveQueryInteger('page', 1),
            'per_page' => $this->positiveQueryInteger('per_page', 20, 100),
            'unread' => $this->booleanQuery('unread'),
            'type' => $this->typeQuery(),
        ];
    }

    private function positiveQueryInteger(string $field, int $default, int $maximum = PHP_INT_MAX): int
    {
        $value = $this->query($field);

        return $value === null ? $default : $this->parsePositiveQueryInteger($value, $field, $maximum);
    }

    private function booleanQuery(string $field): ?bool
    {
        $value = $this->query($field);
        if ($value === null) {
            return null;
        }

        if (! in_array($value, ['true', 'false'], true)) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, $field, 'The query parameter must be true or false.');
        }

        return $value === 'true';
    }

    private function typeQuery(): ?NotificationType
    {
        $value = $this->query('type');
        if ($value === null) {
            return null;
        }

        $type = NotificationType::tryFrom((string) $value);
        if ($type === null) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'type', 'The notification type is invalid.');
        }

        return $type;
    }
}
