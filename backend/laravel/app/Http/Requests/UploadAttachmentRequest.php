<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Services\Attachments\AttachmentValidator;
use App\Services\Attachments\ValidatedAttachment;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;

final class UploadAttachmentRequest extends FormRequest
{
    private ?ValidatedAttachment $attachment = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->getInputSource()->all() !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'Only the attachment file is accepted.', 422, 'attachment');
        }

        $files = $this->allFiles();
        $unknown = array_diff(array_keys($files), ['attachment', 'file']);
        if ($unknown !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The request contains an unsupported file field.', 422, (string) reset($unknown));
        }

        if (array_key_exists('attachment', $files) && array_key_exists('file', $files)) {
            throw new ApiException(ApiErrorCode::INVALID_ATTACHMENT, 'Only one attachment is allowed.', 422, 'attachment');
        }

        $file = $files['attachment'] ?? $files['file'] ?? null;
        if ($file === null) {
            throw new ApiException(ApiErrorCode::MISSING_REQUIRED_FIELD, 'The attachment field is required.', 422, 'attachment');
        }

        if (is_array($file)) {
            throw new ApiException(ApiErrorCode::INVALID_ATTACHMENT, 'Only one attachment is allowed.', 422, 'attachment');
        }

        $this->attachment = app(AttachmentValidator::class)->validate($file);
    }

    public function validatedAttachment(): ValidatedAttachment
    {
        return $this->attachment ?? throw new \LogicException('Attachment was not validated.');
    }
}
