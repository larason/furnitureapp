<?php

namespace App\Console\Commands;

use App\Models\AttachmentUploadCapability;
use Illuminate\Console\Command;

final class PruneAttachmentUploadCapabilitiesCommand extends Command
{
    protected $signature = 'attachments:prune-upload-capabilities';

    protected $description = 'Delete expired and used attachment upload capabilities';

    public function handle(): int
    {
        $now = now();
        $deleted = AttachmentUploadCapability::query()->where('expires_at', '<=', $now)->delete();
        $deleted += AttachmentUploadCapability::query()->whereNotNull('used_at')->delete();

        $this->info("{$deleted} attachment upload capabilities pruned.");

        return self::SUCCESS;
    }
}
