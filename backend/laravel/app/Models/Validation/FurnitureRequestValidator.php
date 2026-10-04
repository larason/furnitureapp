<?php

namespace App\Models\Validation;

use App\Models\FurnitureRequest;
use App\Support\RequestField;
use App\Support\RequestStatus;
use DomainException;

/**
 * Domain invariants for a Furniture Request.
 *
 * Extracted from the model so `FurnitureRequest` stays a small persistence
 * concern. Invoked from the model's `saving` hook; it never mutates state.
 */
final class FurnitureRequestValidator
{
    private const REFERENCE_PATTERN = '/^REQ-[A-Z0-9]{10}$/';

    public static function validate(FurnitureRequest $request): void
    {
        self::assertReference($request);
        self::assertContact($request);
        self::assertProductDetails($request);
        self::assertStyle($request);
        self::assertMessage($request);
        self::assertOptionalSpecs($request);
        self::assertStatus($request);
        self::assertStaffNotes($request);
        self::assertIntakeImmutable($request);
    }

    private static function assertReference(FurnitureRequest $request): void
    {
        $reference = (string) $request->request_reference;

        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new DomainException('Request reference must follow REQ-**********.');
        }

        if ($request->exists && $request->getOriginal('request_reference') !== $request->request_reference) {
            throw new DomainException('Request reference is immutable.');
        }
    }

    private static function assertContact(FurnitureRequest $request): void
    {
        self::assertRequiredString('name', $request->name, FurnitureRequest::MAX_NAME);

        if ($request->email === null && $request->phone === null) {
            throw new DomainException('A request requires at least one of email or phone.');
        }

        if ($request->email !== null) {
            self::assertRequiredString('email', $request->email, FurnitureRequest::MAX_EMAIL);

            if (filter_var($request->email, FILTER_VALIDATE_EMAIL) === false) {
                throw new DomainException('Request email must be a valid email address.');
            }
        }

        if ($request->phone !== null) {
            self::assertRequiredString('phone', $request->phone, FurnitureRequest::MAX_PHONE);
        }
    }

    private static function assertRequiredString(string $field, mixed $value, int $max): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Request {$field} is required.");
        }

        if (mb_strlen(trim($value)) > $max) {
            throw new DomainException("Request {$field} is too long.");
        }
    }

    private static function assertProductDetails(FurnitureRequest $request): void
    {
        $details = $request->product_details;

        if ($details === null) {
            return;
        }

        RequestField::validateProductDetails($details);
    }

    private static function assertStyle(FurnitureRequest $request): void
    {
        if ($request->style === null) {
            return;
        }

        self::assertRequiredString('style', $request->style, FurnitureRequest::MAX_STYLE);
    }

    private static function assertMessage(FurnitureRequest $request): void
    {
        if ($request->message === null) {
            return;
        }

        if (trim($request->message) === '') {
            throw new DomainException('Request message is required.');
        }

        if (mb_strlen(trim($request->message)) > FurnitureRequest::MAX_MESSAGE) {
            throw new DomainException('Request message is too long.');
        }
    }

    private static function assertOptionalSpecs(FurnitureRequest $request): void
    {
        if ($request->quantity !== null) {
            self::assertQuantity($request);
        }

        if ($request->dimensions !== null) {
            RequestField::validateDimensions($request->dimensions);
        }

        if ($request->material !== null) {
            self::assertRequiredString('material', $request->material, FurnitureRequest::MAX_MATERIAL);
        }

        if ($request->color !== null) {
            self::assertRequiredString('color', $request->color, FurnitureRequest::MAX_COLOR);
        }
    }

    private static function assertQuantity(FurnitureRequest $request): void
    {
        if (! is_int($request->quantity) || $request->quantity < 1 || $request->quantity > FurnitureRequest::MAX_QUANTITY) {
            throw new DomainException('Request quantity must be between 1 and '.FurnitureRequest::MAX_QUANTITY.'.');
        }
    }

    private static function assertStatus(FurnitureRequest $request): void
    {
        if (! in_array($request->request_status, RequestStatus::cases(), true)) {
            throw new DomainException('Request status must be a closed V1 request status.');
        }
    }

    private static function assertStaffNotes(FurnitureRequest $request): void
    {
        if ($request->staff_internal_notes !== null && mb_strlen($request->staff_internal_notes) > FurnitureRequest::MAX_STAFF_NOTES) {
            throw new DomainException('Request staff notes are too long.');
        }
    }

    private static function assertIntakeImmutable(FurnitureRequest $request): void
    {
        if (! $request->exists) {
            return;
        }

        foreach (['user_id', 'request_reference', 'product_id', 'product_details', 'style', 'name', 'email', 'phone', 'message', 'quantity', 'dimensions', 'material', 'color'] as $field) {
            if ($request->isDirty($field)) {
                throw new DomainException("Request {$field} is immutable once submitted.");
            }
        }
    }
}
