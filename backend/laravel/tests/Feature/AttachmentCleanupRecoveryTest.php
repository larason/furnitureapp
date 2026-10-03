<?php

namespace Tests\Feature;

use App\Exceptions\AttachmentCleanupRecoveryRequired;
use App\Models\Attachment;
use App\Services\Attachments\AttachFileToParent;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class AttachmentCleanupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_deletes_the_file_when_cleanup_task_persistence_failed(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::set('attachments', $disk);

        app(AttachFileToParent::class)->recoverCleanup('attachments', 'attachments/enquiries/orphan.pdf');

        $this->assertDatabaseCount('attachment_cleanup_tasks', 0);
    }

    public function test_recovery_requeues_when_delete_fails(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('attachments', $disk);

        app(AttachFileToParent::class)->recoverCleanup('attachments', 'attachments/enquiries/orphan.pdf');

        $this->assertDatabaseCount('attachment_cleanup_tasks', 1);
    }

    public function test_delete_surfaces_persistent_recovery_failure_when_queueing_also_fails(): void
    {
        $attachment = Attachment::factory()->create();
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->twice()->andReturn(false);
        Storage::set('attachments', $disk);
        Schema::drop('attachment_cleanup_tasks');

        $this->expectException(AttachmentCleanupRecoveryRequired::class);

        app(AttachFileToParent::class)->delete($attachment);
    }
}
