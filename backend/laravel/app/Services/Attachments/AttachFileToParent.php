<?php

namespace App\Services\Attachments;

use App\Exceptions\AttachmentCleanupRecoveryRequired;
use App\Exceptions\AttachmentCleanupRequired;
use App\Jobs\ProcessAttachmentCleanupTasks;
use App\Models\Attachment;
use App\Models\AttachmentCleanupTask;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        try {
            $this->storage->deleteOrFail($attachment->storage_disk, $attachment->storage_key);
        } catch (Throwable $exception) {
            try {
                $this->queueCleanup($attachment->storage_disk, $attachment->storage_key);
            } catch (Throwable $queueFailure) {
                $this->recoverCleanup($attachment->storage_disk, $attachment->storage_key);
            }

            throw $exception;
        }
    }

    public function queueCleanup(string $disk, string $key): void
    {
        $now = now();

        AttachmentCleanupTask::query()->insertOrIgnore([
            'storage_disk' => $disk,
            'storage_key' => $key,
            'attempts' => 0,
            'last_error' => null,
            'available_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Dispatch once the task row is durably committed so orphaned files are
        // reclaimed without requiring an unrelated parent delete or manual run.
        DB::afterCommit(static function (): void {
            ProcessAttachmentCleanupTasks::dispatch();
        });
    }

    public function recoverCleanup(string $disk, string $key): void
    {
        try {
            $this->storage->deleteOrFail($disk, $key);

            return;
        } catch (Throwable $deleteFailure) {
            try {
                $this->queueCleanup($disk, $key);

                return;
            } catch (Throwable $recoveryFailure) {
                Log::critical('attachment.cleanup_recovery_required', [
                    'disk' => $disk,
                    'storage_key_hash' => hash('sha256', $key),
                    'delete_exception' => $deleteFailure::class,
                    'recovery_exception' => $recoveryFailure::class,
                ]);

                throw new AttachmentCleanupRecoveryRequired($disk, $key, $recoveryFailure);
            }
        }
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
            try {
                $this->storage->deleteOrFail($disk, $key);
            } catch (Throwable $cleanupFailure) {
                if (DB::transactionLevel() === 0) {
                    $this->queueCleanup($disk, $key);
                }

                throw new AttachmentCleanupRequired($disk, $key, $exception, $cleanupFailure);
            }

            throw $exception;
        }
    }
}
