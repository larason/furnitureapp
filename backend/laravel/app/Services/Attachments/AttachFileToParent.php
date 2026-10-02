<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use Throwable;

/**
 * Persists validated attachment metadata for an approved parent (Furniture
 * Request or Enquiry) after writing the private file. If metadata persistence
 * fails, the just-written file is deleted so no orphan remains. Callers own
 * the larger transaction/compensation boundary.
 */
final class AttachFileToParent
{
    public function __construct(private readonly AttachmentStorage $storage) {}

    public function attachRequest(FurnitureRequest $request, ValidatedAttachment $file): Attachment
    {
        return $this->persist($file, 'attachments/requests', ['furniture_request_id' => $request->getKey()]);
    }

    public function attachEnquiry(Enquiry $enquiry, ValidatedAttachment $file): Attachment
    {
        return $this->persist($file, 'attachments/enquiries', ['enquiry_id' => $enquiry->getKey()]);
    }

    public function delete(Attachment $attachment): void
    {
        $this->storage->delete($attachment->storage_disk, $attachment->storage_key);
    }

    /** @param array<string, mixed> $parent */
    private function persist(ValidatedAttachment $file, string $directory, array $parent): Attachment
    {
        $disk = (string) config('attachments.disk');
        $key = $this->storage->store($file, $directory);

        try {
            return Attachment::create([
                ...$parent,
                'storage_disk' => $disk,
                'storage_key' => $key,
                'filename' => $file->filename,
                'content_type' => $file->contentType,
                'size' => $file->size,
            ]);
        } catch (Throwable $exception) {
            $this->storage->delete($disk, $key);

            throw $exception;
        }
    }
}
