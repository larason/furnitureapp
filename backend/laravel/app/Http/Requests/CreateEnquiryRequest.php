<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreatesStaticApiValidationError;
use App\Models\Enquiry;
use App\Services\Attachments\AttachmentValidator;
use App\Services\Attachments\ValidatedAttachment;
use App\Services\Enquiries\EnquiryInput;
use App\Support\ApiErrorCode;
use App\Support\EnquiryCategory;
use App\Support\OrderIdentifier;
use App\Support\ProductIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Single authoritative ENQ-001 schema/normalization boundary.
 *
 * Explicit validation (matching REQ-001) for strict JSON typing and exact error
 * codes. Contact requiredness is actor-aware: anonymous must supply name and a
 * reachable phone/email; an authenticated Customer may omit contact because the
 * creation service derives missing fields from the trusted account. Product
 * visibility and Order ownership are domain checks resolved after schema.
 */
final class CreateEnquiryRequest extends FormRequest
{
    use CreatesStaticApiValidationError;

    private const ALLOWED_FIELDS = [
        'name',
        'phone',
        'email',
        'subject',
        'message',
        'category',
        'product_id',
        'order_id',
    ];

    private const SUBJECT_MIN = 5;

    private const MESSAGE_MIN = 10;

    private const CHARACTERS_SUFFIX = ' characters.';

    private ?EnquiryInput $normalizedInput = null;

    private ?ValidatedAttachment $attachment = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->getInputSource()->all();

        $this->rejectUnknownFileFields();
        $this->rejectMultipartAttachmentField($input);
        self::rejectUnknownFields($input);
        $this->attachment = $this->attachmentFrom($this->file('attachment'));

        $name = self::normalizeOptionalName($input);
        $phone = self::normalizeOptionalPhone($input);
        $email = self::normalizeOptionalEmail($input);
        $subject = self::normalizeSubject($input);
        $message = self::normalizeMessage($input);
        $category = self::normalizeCategory($input);
        $productId = self::normalizeProductId($input);
        $orderId = self::normalizeOrderId($input);

        $this->assertActorRules($name, $phone, $email, $orderId);

        $this->normalizedInput = new EnquiryInput($name, $phone, $email, $subject, $message, $category, $productId, $orderId);
    }

    public function normalizedInput(): EnquiryInput
    {
        return $this->normalizedInput ?? throw new LogicException('ENQ-001 input was not normalized.');
    }

    public function validatedAttachment(): ?ValidatedAttachment
    {
        return $this->attachment;
    }

    private function attachmentFrom(mixed $file): ?ValidatedAttachment
    {
        if ($file === null || $file === []) {
            return null;
        }

        if (is_array($file)) {
            throw self::error(ApiErrorCode::INVALID_ATTACHMENT, 'attachment', 'Only one attachment is allowed.');
        }

        return app(AttachmentValidator::class)->validate($file);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function rejectMultipartAttachmentField(array $input): void
    {
        $contentType = strtolower((string) $this->headers->get('CONTENT_TYPE'));

        if (str_starts_with($contentType, 'multipart/form-data') && array_key_exists('attachment', $input)) {
            throw self::error(ApiErrorCode::INVALID_ATTACHMENT, 'attachment', 'Only one attachment is allowed.');
        }
    }

    /**
     * Uploaded files are not part of `getInputSource()->all()`; reject every
     * top-level file key except the contracted `attachment`.
     */
    private function rejectUnknownFileFields(): void
    {
        $unknown = array_diff(array_keys($this->allFiles()), ['attachment']);

        if ($unknown !== []) {
            $field = (string) reset($unknown);

            throw self::error(ApiErrorCode::INVALID_VALUE, $field, 'The request contains an unsupported file field.');
        }
    }

    /** @param array<string, mixed> $input */
    private static function rejectUnknownFields(array $input): void
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            $field = (string) reset($unknown);

            throw self::error(ApiErrorCode::INVALID_VALUE, $field, 'The request contains an unsupported field.');
        }
    }

    /** @param array<string, mixed> $input */
    private static function normalizeOptionalName(array $input): ?string
    {
        $value = $input['name'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'name', 'The name field must be a string.');
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', trim($value)));

        if ($name === '') {
            return null;
        }

        if (mb_strlen($name) > Enquiry::MAX_NAME) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'name', 'The name field must not be greater than '.Enquiry::MAX_NAME.self::CHARACTERS_SUFFIX);
        }

        return $name;
    }

    /** @param array<string, mixed> $input */
    private static function normalizeOptionalPhone(array $input): ?string
    {
        return FurnitureRequestInputNormalizer::normalizePhone($input);
    }

    /** @param array<string, mixed> $input */
    private static function normalizeOptionalEmail(array $input): ?string
    {
        $value = $input['email'] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'email', 'The email field must be a string.');
        }

        $email = mb_strtolower(trim($value));

        if (mb_strlen($email) > Enquiry::MAX_EMAIL) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'email', 'The email field must not be greater than '.Enquiry::MAX_EMAIL.self::CHARACTERS_SUFFIX);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw self::error(ApiErrorCode::INVALID_FORMAT, 'email', 'The email field format is invalid.');
        }

        return $email;
    }

    /** @param array<string, mixed> $input */
    private static function normalizeSubject(array $input): string
    {
        $value = $input['subject'] ?? null;

        if ($value === null) {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'subject', 'The subject field is required.');
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'subject', 'The subject field must be a string.');
        }

        $subject = trim($value);

        if ($subject === '') {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'subject', 'The subject field is required.');
        }

        if (mb_strlen($subject) < self::SUBJECT_MIN || mb_strlen($subject) > Enquiry::MAX_SUBJECT) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'subject', 'The subject field must be between '.self::SUBJECT_MIN.' and '.Enquiry::MAX_SUBJECT.self::CHARACTERS_SUFFIX);
        }

        return $subject;
    }

    /** @param array<string, mixed> $input */
    private static function normalizeMessage(array $input): string
    {
        $value = $input['message'] ?? null;

        if ($value === null) {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'message', 'The message field is required.');
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'message', 'The message field must be a string.');
        }

        $message = trim($value);

        if ($message === '') {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'message', 'The message field is required.');
        }

        if (mb_strlen($message) < self::MESSAGE_MIN || mb_strlen($message) > Enquiry::MAX_MESSAGE) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'message', 'The message field must be between '.self::MESSAGE_MIN.' and '.Enquiry::MAX_MESSAGE.self::CHARACTERS_SUFFIX);
        }

        return $message;
    }

    /** @param array<string, mixed> $input */
    private static function normalizeCategory(array $input): ?EnquiryCategory
    {
        $value = $input['category'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'category', 'The category field must be a string.');
        }

        $category = EnquiryCategory::tryFrom($value);

        if ($category === null) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'category', 'The category field is not a supported enquiry category.');
        }

        return $category;
    }

    /** @param array<string, mixed> $input */
    private static function normalizeProductId(array $input): ?string
    {
        $value = $input['product_id'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'product_id', 'The product_id field must be a string.');
        }

        if (ProductIdentifier::decode($value) === null) {
            throw self::error(ApiErrorCode::INVALID_FORMAT, 'product_id', 'The product_id field must be a valid product reference.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private static function normalizeOrderId(array $input): ?string
    {
        $value = $input['order_id'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw self::error(ApiErrorCode::INVALID_TYPE, 'order_id', 'The order_id field must be a string.');
        }

        if (OrderIdentifier::decode($value) === null) {
            throw self::error(ApiErrorCode::INVALID_FORMAT, 'order_id', 'The order_id field must be a valid order reference.');
        }

        return $value;
    }

    private function assertActorRules(?string $name, ?string $phone, ?string $email, ?string $orderId): void
    {
        if ($this->user() !== null) {
            return;
        }

        if ($name === null) {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'name', 'The name field is required.');
        }

        if ($phone === null && $email === null) {
            throw self::error(ApiErrorCode::MISSING_REQUIRED_FIELD, 'phone', 'At least one of phone or email is required.');
        }

        if ($orderId !== null) {
            throw self::error(ApiErrorCode::INVALID_VALUE, 'order_id', 'An anonymous enquiry cannot reference an order.');
        }
    }
}
