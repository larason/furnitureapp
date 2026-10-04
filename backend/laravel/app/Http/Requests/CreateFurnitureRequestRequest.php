<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreatesStaticApiValidationError;
use App\Models\FurnitureRequest;
use App\Services\Attachments\AttachmentValidator;
use App\Services\Attachments\ValidatedAttachment;
use App\Services\Requests\FurnitureRequestInput;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Single authoritative REQ-001 schema/normalization boundary.
 *
 * Validation is explicit instead of Laravel-rule driven because the frozen
 * contract needs JSON-number semantics, strict scalar typing, nested
 * dimension key checks, and exact error codes the framework's rule-name
 * heuristics cannot express. Phase 10.2 validates shape only; Product
 * existence/active/published/MADE_TO_ORDER eligibility stays Phase 10.4.
 */
final class CreateFurnitureRequestRequest extends FormRequest
{
    use CreatesStaticApiValidationError;

    private const ALLOWED_FIELDS = [
        'product_id',
        'quantity',
        'name',
        'phone',
        'email',
        'dimensions',
        'material',
        'color',
        'notes',
    ];

    private ?FurnitureRequestInput $normalizedInput = null;

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
        $input = $this->rawInput();

        $this->rejectUnknownFileFields();
        $this->rejectMultipartAttachmentField($input);
        $this->rejectUnknownFields($input);
        $this->attachment = $this->attachmentFrom($this->file('attachment'));

        $this->normalizedInput = new FurnitureRequestInput(
            productId: FurnitureRequestInputNormalizer::normalizeProductId($input),
            quantity: FurnitureRequestInputNormalizer::normalizeQuantity($input),
            name: FurnitureRequestInputNormalizer::normalizeName($input),
            phone: FurnitureRequestInputNormalizer::normalizePhone($input),
            email: FurnitureRequestInputNormalizer::normalizeEmail($input),
            dimensions: FurnitureRequestInputNormalizer::normalizeDimensions($input),
            material: FurnitureRequestInputNormalizer::normalizeOptionalText($input, 'material', FurnitureRequest::MAX_MATERIAL),
            color: FurnitureRequestInputNormalizer::normalizeOptionalText($input, 'color', FurnitureRequest::MAX_COLOR),
            notes: FurnitureRequestInputNormalizer::normalizeOptionalText($input, 'notes', FurnitureRequest::MAX_MESSAGE),
        );

        FurnitureRequestInputNormalizer::assertContactChannel($this->normalizedInput);
    }

    public function normalizedInput(): FurnitureRequestInput
    {
        return $this->normalizedInput ?? throw new LogicException('REQ-001 input was not normalized.');
    }

    public function validatedAttachment(): ?ValidatedAttachment
    {
        return $this->attachment;
    }

    /** @return array<string, mixed> */
    private function rawInput(): array
    {
        $input = $this->getInputSource()->all();

        if (! $this->isMultipart()) {
            return $input;
        }

        if (isset($input['quantity']) && is_string($input['quantity']) && preg_match('/^-?\d+$/', $input['quantity']) === 1) {
            $input['quantity'] = (int) $input['quantity'];
        }

        if (isset($input['dimensions']) && is_array($input['dimensions'])) {
            $input['dimensions'] = FurnitureRequestInputNormalizer::decodeMultipartDimensions($input['dimensions']);
        }

        return $input;
    }

    private function isMultipart(): bool
    {
        return str_starts_with(strtolower((string) $this->headers->get('CONTENT_TYPE')), 'multipart/form-data');
    }

    /**
     * Laravel keeps uploaded files out of `getInputSource()->all()`, so the
     * scalar allow-list cannot see them. Reject every top-level uploaded file
     * key except the contracted `attachment` instead of silently discarding it.
     */
    private function rejectUnknownFileFields(): void
    {
        $unknown = array_diff(array_keys($this->allFiles()), ['attachment']);

        if ($unknown !== []) {
            $field = (string) reset($unknown);

            throw self::error(ApiErrorCode::INVALID_VALUE, $field, 'The request contains an unsupported file field.');
        }
    }

    /**
     * A multipart `attachment` value in the scalar fields (rather than a single
     * uploaded file, which Laravel exposes via `allFiles()`) is a text field or
     * a multiple-file array; both are invalid in V1.
     *
     * @param  array<string, mixed>  $input
     */
    private function rejectMultipartAttachmentField(array $input): void
    {
        if ($this->isMultipart() && array_key_exists('attachment', $input)) {
            throw self::error(ApiErrorCode::INVALID_ATTACHMENT, 'attachment', 'Only one attachment is allowed.');
        }
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

    /** @param array<string, mixed> $input */
    private function rejectUnknownFields(array $input): void
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            $field = (string) reset($unknown);

            throw self::error(ApiErrorCode::INVALID_VALUE, $field, 'The request contains an unsupported field.');
        }
    }
}
