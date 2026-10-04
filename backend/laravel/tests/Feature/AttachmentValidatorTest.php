<?php

namespace Tests\Feature;

use App\Exceptions\Api\ApiException;
use App\Services\Attachments\AttachmentValidator;
use App\Support\ApiErrorCode;
use Illuminate\Http\UploadedFile;
use Tests\Support\CreatesAttachmentFiles;
use Tests\TestCase;

class AttachmentValidatorTest extends TestCase
{
    use CreatesAttachmentFiles;

    public function test_real_allowed_types_are_accepted_with_server_detected_metadata(): void
    {
        $cases = [
            [$this->jpegUpload('photo.jpg'), 'image/jpeg', 'jpg'],
            [$this->pngUpload('photo.png'), 'image/png', 'png'],
            [$this->webpUpload('photo.webp'), 'image/webp', 'webp'],
            [$this->pdfUpload('document.pdf'), 'application/pdf', 'pdf'],
        ];

        foreach ($cases as [$file, $contentType, $extension]) {
            $validated = $this->validator()->validate($file);

            $this->assertSame($contentType, $validated->contentType);
            $this->assertSame($extension, $validated->extension);
            $this->assertSame($file->getSize(), $validated->size);
        }
    }

    public function test_unsupported_content_types_are_rejected(): void
    {
        $files = [
            $this->fakeFile('image.gif', 'GIF89a'.str_repeat("\x00", 20)),
            $this->fakeFile('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            $this->textUpload('notes.txt'),
            $this->fakeFile('archive.zip', "PK\x03\x04".str_repeat("\x00", 20)),
        ];

        foreach ($files as $file) {
            $this->assertError(ApiErrorCode::UNSUPPORTED_ATTACHMENT_TYPE, 422, $file);
        }
    }

    public function test_oversize_attachment_is_rejected_with_413(): void
    {
        $this->assertError(ApiErrorCode::ATTACHMENT_TOO_LARGE, 413, UploadedFile::fake()->create('big.png', 5121));
    }

    public function test_zero_byte_attachment_is_invalid(): void
    {
        $this->assertError(ApiErrorCode::INVALID_ATTACHMENT, 422, $this->zeroByteUpload());
    }

    public function test_extension_is_not_trusted_and_server_detection_wins(): void
    {
        $validated = $this->validator()->validate($this->fakeFile('photo.jpg', (string) $this->pdfUpload()->getContent()));

        $this->assertSame('application/pdf', $validated->contentType);
        $this->assertSame('pdf', $validated->extension);
    }

    public function test_plain_text_named_png_is_rejected(): void
    {
        $this->assertError(ApiErrorCode::UNSUPPORTED_ATTACHMENT_TYPE, 422, $this->fakeFile('photo.png', 'this is not an image'));
    }

    public function test_filename_traversal_is_neutralized(): void
    {
        $validated = $this->validator()->validate($this->fakeFile('../../secret.pdf', (string) $this->pdfUpload()->getContent()));

        $this->assertSame('secret.pdf', $validated->filename);
        $this->assertStringNotContainsString('/', $validated->filename);
        $this->assertStringNotContainsString('\\', $validated->filename);
    }

    public function test_control_characters_are_removed_from_the_display_name(): void
    {
        $validated = $this->validator()->validate($this->fakeFile("dark\x00\x1f.pdf", (string) $this->pdfUpload()->getContent()));

        $this->assertSame('dark.pdf', $validated->filename);
    }

    private function assertError(ApiErrorCode $code, int $status, UploadedFile $file): void
    {
        try {
            $this->validator()->validate($file);
            $this->fail('Expected the attachment to be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame($code, $exception->errorCode());
            $this->assertSame($status, $exception->status());
            $this->assertSame('attachment', $exception->field());
        }
    }

    private function validator(): AttachmentValidator
    {
        return app(AttachmentValidator::class);
    }
}
