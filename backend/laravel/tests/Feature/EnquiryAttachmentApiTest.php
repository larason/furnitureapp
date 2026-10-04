<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Enquiry;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\SubmitsAttachmentCreation;
use Tests\TestCase;

class EnquiryAttachmentApiTest extends TestCase
{
    use CreatesAttachmentFiles;
    use RefreshDatabase;
    use SubmitsAttachmentCreation;

    private const URL = '/api/v1/enquiries';

    private const FIELDS = [
        'name' => 'Asha Mwangi',
        'email' => 'asha@example.com',
        'subject' => 'Delivery question',
        'message' => 'Do you deliver furniture to Dodoma?',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['enquiries.route_enabled' => true]);
        Storage::fake('attachments');
    }

    public function test_json_creation_without_attachment_still_works(): void
    {
        $this->postJson(self::URL, self::FIELDS)->assertStatus(201)->assertJsonPath('data.attachments', []);
    }

    public function test_multipart_creation_with_a_valid_attachment(): void
    {
        $response = $this->multipart(self::FIELDS, $this->pdfUpload('question.pdf'))->assertStatus(201);
        $this->assertNull($response->headers->get('X-Upload-Token'));

        $attachment = $response->json('data.attachments.0');

        $this->assertStringStartsWith('att_', $attachment['id']);
        $this->assertSame('question.pdf', $attachment['filename']);
        $this->assertSame('application/pdf', $attachment['content_type']);
        $this->assertNull($attachment['url']);

        $stored = Attachment::query()->sole();
        $this->assertSame($stored->enquiry_id, Enquiry::query()->sole()->id);
        $this->assertNull($stored->furniture_request_id);
        Storage::disk($stored->storage_disk)->assertExists($stored->storage_key);
    }

    public function test_cleanup_task_survives_when_inline_attachment_mapping_and_delete_fail(): void
    {
        Schema::drop('attachments');

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once()->andReturn(true);
        $disk->shouldReceive('delete')->andReturn(false);
        Storage::set('attachments', $disk);

        $this->multipart(self::FIELDS, $this->pdfUpload('question.pdf'))->assertStatus(500);

        $this->assertDatabaseCount('attachment_cleanup_tasks', 1);
    }

    public function test_unsupported_attachment_type_persists_nothing(): void
    {
        $this->multipart(self::FIELDS, $this->textUpload('notes.txt'))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'UNSUPPORTED_ATTACHMENT_TYPE');

        $this->assertDatabaseCount('enquiries', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_storage_write_failure_aborts_creation_and_persists_nothing(): void
    {
        $this->failingDisk();

        $this->multipart(self::FIELDS, $this->pdfUpload('question.pdf'))
            ->assertStatus(500)
            ->assertJsonPath('errors.0.code', 'INTERNAL_SERVER_ERROR');

        $this->assertDatabaseCount('enquiries', 0);
        $this->assertDatabaseCount('attachments', 0);
    }
}
