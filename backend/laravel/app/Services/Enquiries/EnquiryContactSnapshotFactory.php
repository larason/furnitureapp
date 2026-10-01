<?php

namespace App\Services\Enquiries;

use App\Exceptions\Api\ApiException;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\PhoneNumber;

/**
 * Builds the historical Enquiry contact snapshot.
 *
 * Explicit valid submitted contact wins; a missing authenticated field falls
 * back to the trusted local account/profile. A supplied (invalid) field is
 * rejected at the schema boundary and never silently replaced. Derived values
 * that are unusable are treated as absent. The final snapshot must contain a
 * name and at least one reachable phone/email, otherwise creation fails.
 */
final class EnquiryContactSnapshotFactory
{
    public function build(?User $actor, ?string $name, ?string $phone, ?string $email): EnquiryContactSnapshot
    {
        $resolvedName = $name ?? self::usableName($actor?->name);

        if ($resolvedName === null) {
            throw new ApiException(ApiErrorCode::MISSING_REQUIRED_FIELD, 'The name field is required.', 422, 'name');
        }

        $resolvedPhone = $phone ?? self::usablePhone($actor?->phone);
        $resolvedEmail = $email ?? self::usableEmail($actor?->email);

        if ($resolvedPhone === null && $resolvedEmail === null) {
            throw new ApiException(ApiErrorCode::MISSING_REQUIRED_FIELD, 'At least one of phone or email is required.', 422, 'phone');
        }

        return new EnquiryContactSnapshot($resolvedName, $resolvedPhone, $resolvedEmail);
    }

    private static function usableName(?string $value): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', trim($value ?? '')));

        if ($name === '' || mb_strlen($name) > Enquiry::MAX_NAME) {
            return null;
        }

        return $name;
    }

    private static function usablePhone(?string $value): ?string
    {
        $phone = trim($value ?? '');

        return PhoneNumber::isWellFormed($phone) && mb_strlen($phone) <= PhoneNumber::MAX_LENGTH ? $phone : null;
    }

    private static function usableEmail(?string $value): ?string
    {
        $email = mb_strtolower(trim($value ?? ''));

        if ($email === '' || mb_strlen($email) > Enquiry::MAX_EMAIL) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }
}
