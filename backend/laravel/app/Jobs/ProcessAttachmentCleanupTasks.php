<?php

namespace App\Jobs;

use App\Models\AttachmentCleanupTask;
use App\Services\Attachments\AttachmentStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessAttachmentCleanupTasks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(AttachmentStorage $storage): void
    {
        AttachmentCleanupTask::query()
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (AttachmentCleanupTask $task) use ($storage): void {
                try {
                    $storage->deleteOrFail($task->storage_disk, $task->storage_key);
                    $task->delete();
                } catch (Throwable $exception) {
                    DB::table($task->getTable())
                        ->where('id', $task->getKey())
                        ->update([
                            'attempts' => $task->attempts + 1,
                            'last_error' => $exception::class,
                            'available_at' => now()->addSeconds($this->retryDelay($task->attempts + 1)),
                            'updated_at' => now(),
                        ]);

                    Log::warning('attachment.cleanup_task_failed', [
                        'task_id' => $task->getKey(),
                        'attempts' => $task->attempts + 1,
                        'exception' => $exception::class,
                    ]);
                }
            });

        $this->scheduleNextRun();
    }

    private function retryDelay(int $attempts): int
    {
        $backoff = $this->backoff();

        return $backoff[min($attempts - 1, count($backoff) - 1)];
    }

    private function scheduleNextRun(): void
    {
        if ($this->job === null || config('queue.connections.'.config('queue.default').'.driver') === 'sync') {
            return;
        }

        $next = AttachmentCleanupTask::query()->orderBy('available_at')->first();

        if ($next === null) {
            return;
        }

        $delay = max(0, now()->diffInSeconds($next->available_at, false));
        self::dispatch()->delay($delay);
    }
}
