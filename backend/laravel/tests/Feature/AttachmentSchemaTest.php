<?php

namespace Tests\Feature;

use App\Jobs\ProcessAttachmentCleanupTasks;
use App\Models\Attachment;
use App\Models\AttachmentCleanupTask;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Services\Attachments\AttachmentStorage;
use DomainException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class AttachmentDeleteRollbackException extends RuntimeException {}

class AttachmentSchemaTest extends TestCase
{
    use DatabaseMigrations;

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('attachments'));
        $this->assertTrue(Schema::hasColumns('attachments', [
            'id',
            'furniture_request_id',
            'enquiry_id',
            'storage_disk',
            'storage_key',
            'filename',
            'content_type',
            'size',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_attachment_belongs_to_a_furniture_request(): void
    {
        $request = FurnitureRequest::factory()->create();
        $attachment = Attachment::factory()->create(['furniture_request_id' => $request->id]);

        $this->assertTrue($attachment->furnitureRequest->is($request));
        $this->assertNull($attachment->enquiry_id);
    }

    public function test_attachment_belongs_to_an_enquiry(): void
    {
        $enquiry = Enquiry::factory()->create();
        $attachment = Attachment::factory()->create([
            'furniture_request_id' => null,
            'enquiry_id' => $enquiry->id,
        ]);

        $this->assertTrue($attachment->enquiry->is($enquiry));
        $this->assertNull($attachment->furniture_request_id);
    }

    public function test_attachment_requires_exactly_one_parent(): void
    {
        $this->expectException(DomainException::class);

        Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => null]);
    }

    public function test_attachment_rejects_two_parents(): void
    {
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();

        $this->expectException(DomainException::class);

        Attachment::factory()->create([
            'furniture_request_id' => $request->id,
            'enquiry_id' => $enquiry->id,
        ]);
    }

    public function test_only_one_attachment_per_furniture_request(): void
    {
        $request = FurnitureRequest::factory()->create();
        Attachment::factory()->create(['furniture_request_id' => $request->id]);

        $this->expectException(QueryException::class);

        Attachment::factory()->create(['furniture_request_id' => $request->id]);
    }

    public function test_only_one_attachment_per_enquiry(): void
    {
        $enquiry = Enquiry::factory()->create();
        Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);

        $this->expectException(QueryException::class);

        Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);
    }

    public function test_deleting_parent_cascades_attachment_metadata(): void
    {
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();
        $requestAttachment = Attachment::factory()->create(['furniture_request_id' => $request->id]);
        $enquiryAttachment = Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);

        $request->delete();
        $enquiry->delete();

        $this->assertNull(Attachment::find($requestAttachment->id));
        $this->assertNull(Attachment::find($enquiryAttachment->id));
    }

    public function test_deleting_parent_removes_stored_attachment_files(): void
    {
        Storage::fake('attachments');

        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();
        $requestAttachment = Attachment::factory()->create(['furniture_request_id' => $request->id]);
        $enquiryAttachment = Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);

        Storage::disk('attachments')->put($requestAttachment->storage_key, 'request');
        Storage::disk('attachments')->put($enquiryAttachment->storage_key, 'enquiry');

        $request->delete();
        $enquiry->delete();

        Storage::disk('attachments')->assertMissing($requestAttachment->storage_key);
        Storage::disk('attachments')->assertMissing($enquiryAttachment->storage_key);
    }

    public function test_bulk_enquiry_delete_removes_stored_attachment_files(): void
    {
        Storage::fake('attachments');

        $first = Enquiry::factory()->create();
        $second = Enquiry::factory()->create();
        $firstAttachment = Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $first->id]);
        $secondAttachment = Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $second->id]);
        Storage::disk('attachments')->put($firstAttachment->storage_key, 'first');
        Storage::disk('attachments')->put($secondAttachment->storage_key, 'second');

        $deleted = Enquiry::query()->select('subject')->whereKey([$first->id, $second->id])->delete();

        $this->assertSame(2, $deleted);
        Storage::disk('attachments')->assertMissing($firstAttachment->storage_key);
        Storage::disk('attachments')->assertMissing($secondAttachment->storage_key);
        $this->assertNull(Attachment::find($firstAttachment->id));
        $this->assertNull(Attachment::find($secondAttachment->id));
    }

    public function test_bulk_request_delete_removes_stored_attachment_files(): void
    {
        Storage::fake('attachments');

        $first = FurnitureRequest::factory()->create();
        $second = FurnitureRequest::factory()->create();
        $firstAttachment = Attachment::factory()->create(['furniture_request_id' => $first->id]);
        $secondAttachment = Attachment::factory()->create(['furniture_request_id' => $second->id]);
        Storage::disk('attachments')->put($firstAttachment->storage_key, 'first');
        Storage::disk('attachments')->put($secondAttachment->storage_key, 'second');

        $deleted = FurnitureRequest::query()->whereKey([$first->id, $second->id])->delete();

        $this->assertSame(2, $deleted);
        Storage::disk('attachments')->assertMissing($firstAttachment->storage_key);
        Storage::disk('attachments')->assertMissing($secondAttachment->storage_key);
        $this->assertNull(Attachment::find($firstAttachment->id));
        $this->assertNull(Attachment::find($secondAttachment->id));
    }

    public function test_failed_cleanup_remains_retryable_after_parent_delete(): void
    {
        Queue::fake();

        $enquiry = Enquiry::factory()->create();
        $attachment = Attachment::factory()->create([
            'furniture_request_id' => null,
            'enquiry_id' => $enquiry->id,
        ]);

        Enquiry::query()->whereKey($enquiry->id)->delete();

        $task = AttachmentCleanupTask::query()->sole();
        $this->assertSame($attachment->storage_key, $task->storage_key);
        Queue::assertPushed(ProcessAttachmentCleanupTasks::class);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('attachments', $disk);

        (new ProcessAttachmentCleanupTasks)->handle(app(AttachmentStorage::class));

        $this->assertSame(1, AttachmentCleanupTask::query()->sole()->attempts);
    }

    public function test_cleanup_command_processes_tasks_beyond_one_batch(): void
    {
        Storage::fake('attachments');
        $now = now();
        $tasks = [];

        for ($index = 0; $index < 101; $index++) {
            $tasks[] = [
                'storage_disk' => 'attachments',
                'storage_key' => "attachments/requests/task-{$index}.png",
                'attempts' => 0,
                'last_error' => null,
                'available_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        AttachmentCleanupTask::query()->insert($tasks);

        $this->artisan('attachments:cleanup')->assertSuccessful();

        $this->assertSame(0, AttachmentCleanupTask::query()->count());
    }

    public function test_attachment_cleanup_command_is_scheduled_as_a_retry_safety_net(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('attachments:cleanup')
            ->assertSuccessful();
    }

    public function test_rolled_back_request_delete_keeps_the_stored_attachment_file(): void
    {
        Storage::fake('attachments');

        $request = FurnitureRequest::factory()->create();
        $attachment = Attachment::factory()->create(['furniture_request_id' => $request->id]);
        Storage::disk('attachments')->put($attachment->storage_key, 'request');

        try {
            DB::transaction(function () use ($request): void {
                $request->delete();

                throw new AttachmentDeleteRollbackException('force rollback');
            });
        } catch (AttachmentDeleteRollbackException) {
            // Expected: the transaction rolled back.
        }

        Storage::disk('attachments')->assertExists($attachment->storage_key);
        $this->assertNotNull(Attachment::find($attachment->id));
        $this->assertNotNull(FurnitureRequest::find($request->id));
    }

    public function test_rolled_back_enquiry_delete_keeps_the_stored_attachment_file(): void
    {
        Storage::fake('attachments');

        $enquiry = Enquiry::factory()->create();
        $attachment = Attachment::factory()->create(['furniture_request_id' => null, 'enquiry_id' => $enquiry->id]);
        Storage::disk('attachments')->put($attachment->storage_key, 'enquiry');

        try {
            DB::transaction(function () use ($enquiry): void {
                $enquiry->delete();

                throw new AttachmentDeleteRollbackException('force rollback');
            });
        } catch (AttachmentDeleteRollbackException) {
            // Expected: the transaction rolled back.
        }

        Storage::disk('attachments')->assertExists($attachment->storage_key);
        $this->assertNotNull(Attachment::find($attachment->id));
        $this->assertNotNull(Enquiry::find($enquiry->id));
    }
}
