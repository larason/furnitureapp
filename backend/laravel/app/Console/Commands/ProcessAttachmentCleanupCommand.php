<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAttachmentCleanupTasks;
use App\Models\AttachmentCleanupTask;
use App\Services\Attachments\AttachmentStorage;
use Illuminate\Console\Command;

final class ProcessAttachmentCleanupCommand extends Command
{
    protected $signature = 'attachments:cleanup';

    protected $description = 'Process pending private attachment cleanup tasks';

    public function handle(): int
    {
        do {
            $job = new ProcessAttachmentCleanupTasks;
            $job->handle(app(AttachmentStorage::class));
            $hasDueTasks = AttachmentCleanupTask::query()
                ->where('available_at', '<=', now())
                ->exists();
        } while ($hasDueTasks);

        $this->info('Attachment cleanup tasks processed.');

        return self::SUCCESS;
    }
}
