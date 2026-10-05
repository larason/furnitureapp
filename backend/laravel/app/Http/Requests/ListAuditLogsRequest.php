<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreatesApiValidationError;
use App\Http\Requests\Concerns\ParsesIso8601UtcQueryDate;
use App\Http\Requests\Concerns\ParsesPositiveQueryInteger;
use App\Services\AuditLogs\AuditLogQuery;
use App\Support\ApiErrorCode;
use App\Support\AuditAction;
use App\Support\AuditResourceType;
use App\Support\UserIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ListAuditLogsRequest extends FormRequest
{
    use CreatesApiValidationError;
    use ParsesIso8601UtcQueryDate;
    use ParsesPositiveQueryInteger;

    private const ALLOWED_FIELDS = ['actor', 'action', 'resource_type', 'resource_id', 'created_from', 'created_to', 'page', 'per_page'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function normalizedQuery(): AuditLogQuery
    {
        $input = $this->query();
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);
        if ($unknown !== []) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, (string) reset($unknown), 'The query parameter is not supported.');
        }

        $createdFrom = $this->queryDate($input, 'created_from');
        $createdTo = $this->queryDate($input, 'created_to');
        if ($createdFrom !== null && $createdTo !== null && $createdFrom->greaterThan($createdTo)) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'created_from', 'The created_from value must not be later than created_to.');
        }

        return new AuditLogQuery(
            actorId: $this->actor($input),
            action: $this->auditEnum($input, 'action', AuditAction::class),
            resourceType: $this->auditEnum($input, 'resource_type', AuditResourceType::class),
            resourceId: $this->resourceId($input),
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            page: $this->optionalPositiveQueryInteger($input, 'page', 1),
            perPage: $this->optionalPositiveQueryInteger($input, 'per_page', 20, 100),
        );
    }

    /** @param array<string, mixed> $input */
    private function actor(array $input): ?int
    {
        $value = $this->optionalString($input, 'actor');
        if ($value === null) {
            return null;
        }

        $actorId = UserIdentifier::decode($value);
        if ($actorId === null) {
            throw $this->error(ApiErrorCode::INVALID_FORMAT, 'actor', 'The actor parameter must be a valid reference.');
        }

        return $actorId;
    }

    /** @param array<string, mixed> $input */
    private function auditEnum(array $input, string $field, string $enumClass): mixed
    {
        $value = $this->optionalString($input, $field);
        if ($value === null) {
            return null;
        }

        $enum = $enumClass::tryFrom($value);
        if ($enum === null) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, $field, "The {$field} parameter is invalid.");
        }

        return $enum;
    }

    /** @param array<string, mixed> $input */
    private function resourceId(array $input): ?string
    {
        $value = $this->optionalString($input, 'resource_id');
        if ($value !== null && ($value === '' || mb_strlen($value) > 255)) {
            throw $this->error(ApiErrorCode::INVALID_VALUE, 'resource_id', 'The resource_id parameter is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function queryDate(array $input, string $field): ?CarbonImmutable
    {
        $value = $this->optionalString($input, $field);

        return $value === null ? null : $this->parseIso8601UtcQueryDate($value, $field);
    }

    /** @param array<string, mixed> $input */
    private function optionalString(array $input, string $field): ?string
    {
        if (! array_key_exists($field, $input)) {
            return null;
        }

        if (! is_string($input[$field])) {
            throw $this->error(ApiErrorCode::INVALID_TYPE, $field, "The {$field} parameter must be a string.");
        }

        return $input[$field];
    }
}
