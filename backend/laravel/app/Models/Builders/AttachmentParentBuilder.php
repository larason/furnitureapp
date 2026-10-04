<?php

namespace App\Models\Builders;

use App\Jobs\ProcessAttachmentCleanupTasks;
use App\Models\Attachment;
use App\Models\AttachmentCleanupTask;
use App\Models\AttachmentUploadCapability;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Removes private attachment files for every Eloquent deletion path.
 *
 * The cleanup runs from the builder's `onDelete` handler, which Laravel calls
 * for both mass deletes and single-instance deletes (via
 * Model::performDeleteOnModel). Mass deletes do not fire model events, so a
 * plain builder delete would let the database cascade the attachment metadata
 * while the stored files are orphaned.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
final class AttachmentParentBuilder extends Builder
{
    private const ATTACHMENT_BATCH_SIZE = 500;

    public function __construct(mixed $query)
    {
        parent::__construct($query);

        $this->onDelete(fn (): int => $this->deleteWithAttachmentCleanup());
    }

    public function forceDelete(): int
    {
        return $this->deleteWithAttachmentCleanup();
    }

    private function deleteWithAttachmentCleanup(): int
    {
        return DB::transaction(function (): int {
            $hasCleanupTasks = false;
            $deleted = 0;
            $model = $this->getModel();
            $keyName = $model->getKeyName();
            $qualifiedKeyName = $model->getQualifiedKeyName();
            $foreignKey = $this->attachmentForeignKey($model);
            $capabilityParentType = $this->capabilityParentType($model);
            $parents = clone $this;

            $parents
                ->select($qualifiedKeyName)
                ->lockForUpdate()
                ->chunkById(
                    self::ATTACHMENT_BATCH_SIZE,
                    function (Collection $parents) use (&$deleted, &$hasCleanupTasks, $capabilityParentType, $foreignKey): void {
                        $parentIds = $parents->modelKeys();
                        AttachmentUploadCapability::query()
                            ->where('parent_type', $capabilityParentType)
                            ->whereIn('parent_id', $parentIds)
                            ->delete();
                        $attachments = Attachment::query()
                            ->whereIn($foreignKey, $parentIds)
                            ->get(['storage_disk', 'storage_key']);

                        if ($attachments->isNotEmpty()) {
                            $now = now();
                            AttachmentCleanupTask::query()->insertOrIgnore(
                                $attachments->map(static fn (Attachment $attachment): array => [
                                    'storage_disk' => $attachment->storage_disk,
                                    'storage_key' => $attachment->storage_key,
                                    'attempts' => 0,
                                    'last_error' => null,
                                    'available_at' => $now,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ])->all(),
                            );
                            $hasCleanupTasks = true;
                        }

                        $deleted += (int) $this->getModel()
                            ->newQueryWithoutScopes()
                            ->whereKey($parentIds)
                            ->toBase()
                            ->delete();
                    },
                    $qualifiedKeyName,
                    $keyName,
                );

            if ($hasCleanupTasks) {
                DB::afterCommit(static function (): void {
                    try {
                        ProcessAttachmentCleanupTasks::dispatch();
                    } catch (Throwable $exception) {
                        Log::error('attachment.cleanup_dispatch_failed', [
                            'exception' => $exception::class,
                        ]);
                    }
                });
            }

            return $deleted;
        });
    }

    private function attachmentForeignKey(Model $model): string
    {
        return match (true) {
            $model instanceof Enquiry => 'enquiry_id',
            $model instanceof FurnitureRequest => 'furniture_request_id',
            default => throw new \LogicException('Attachment cleanup is not supported for this model.'),
        };
    }

    private function capabilityParentType(Model $model): string
    {
        return match (true) {
            $model instanceof Enquiry => AttachmentUploadCapability::ENQUIRY_PARENT_TYPE,
            $model instanceof FurnitureRequest => AttachmentUploadCapability::FURNITURE_REQUEST_PARENT_TYPE,
            default => throw new \LogicException('Attachment cleanup is not supported for this model.'),
        };
    }
}
