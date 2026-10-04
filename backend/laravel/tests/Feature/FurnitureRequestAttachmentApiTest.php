<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\FurnitureRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Support\CreatesAttachmentFiles;
use Tests\TestCase;

class FurnitureRequestAttachmentApiTest extends TestCase
{
    use CreatesAttachmentFiles;
    use RefreshDatabase;

    private const URL = '/api/v1/requests';

    private const FIELDS = [
        'name' => 'Asha Mwangi',
        'phone' => '+255700000001',
        'notes' => 'Please make something similar',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['requests.route_enabled' => true]);
        Storage::fake('attachments');
    }

    public function test_json_creation_without_attachment_still_works(): void
    {
        $this->postJson(self::URL, self::FIELDS)
            ->assertStatus(201)
            ->assertJsonPath('data.attachments', []);
    }

    public function test_multipart_creation_without_attachment_works(): void
    {
        $this->multipart(self::FIELDS)
            ->assertStatus(201)
            ->assertJsonPath('data.attachments', []);
    }

    public function test_multipart_creation_with_a_valid_attachment(): void
    {
        $response = $this->multipart(self::FIELDS, $this->pngUpload('reference.png'))->assertStatus(201);
        $this->assertNull($response->headers->get('X-Upload-Token'));

        $attachment = $response->json('data.attachments.0');

        $this->assertStringStartsWith('att_', $attachment['id']);
        $this->assertSame('reference.png', $attachment['filename']);
        $this->assertSame('image/png', $attachment['content_type']);
        $this->assertSame(['id', 'filename', 'content_type', 'size', 'url'], array_keys($attachment));
        $this->assertNull($attachment['url']);

        $stored = Attachment::query()->sole();
        $this->assertSame('image/png', $stored->content_type);
        $this->assertSame($stored->size, $attachment['size']);
        Storage::disk($stored->storage_disk)->assertExists($stored->storage_key);
    }

    public function test_unsupported_type_is_rejected_without_persisting_anything(): void
    {
        $this->multipart(self::FIELDS, $this->textUpload('notes.txt'))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'UNSUPPORTED_ATTACHMENT_TYPE')
            ->assertJsonPath('errors.0.field', 'attachment');

        $this->assertDatabaseCount('furniture_requests', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_oversize_attachment_is_rejected_with_413(): void
    {
        $this->multipart(self::FIELDS, UploadedFile::fake()->create('big.png', 5121))
            ->assertStatus(413)
            ->assertJsonPath('errors.0.code', 'ATTACHMENT_TOO_LARGE');

        $this->assertDatabaseCount('furniture_requests', 0);
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_multiple_attachments_are_rejected(): void
    {
        $this->multipart(self::FIELDS, [$this->pngUpload('one.png'), $this->pngUpload('two.png')])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_ATTACHMENT');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_invalid_scalar_with_valid_attachment_persists_nothing(): void
    {
        $fields = ['phone' => '+255700000001', 'notes' => 'no name supplied'];

        $this->multipart($fields, $this->pngUpload('reference.png'))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->assertDatabaseCount('furniture_requests', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_base64_json_attachment_is_rejected(): void
    {
        $this->postJson(self::URL, [...self::FIELDS, 'attachment' => 'base64-data'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE');
    }

    public function test_storage_write_failure_aborts_creation_and_persists_nothing(): void
    {
        $this->failingDisk();

        $this->multipart(self::FIELDS, $this->pngUpload('reference.png'))
            ->assertStatus(500)
            ->assertJsonPath('errors.0.code', 'INTERNAL_SERVER_ERROR');

        $this->assertDatabaseCount('furniture_requests', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_cleanup_task_survives_when_inline_attachment_mapping_and_delete_fail(): void
    {
        Schema::drop('attachments');

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once()->andReturn(true);
        $disk->shouldReceive('delete')->andReturn(false);
        Storage::set('attachments', $disk);

        $this->multipart(self::FIELDS, $this->pngUpload('reference.png'))->assertStatus(500);

        // The orphaned file must retain a durable cleanup task even though the
        // failed creation request is rolled back.
        $this->assertDatabaseCount('attachment_cleanup_tasks', 1);
    }

    public function test_attachment_creation_has_no_commerce_side_effects(): void
    {
        $this->multipart(self::FIELDS, $this->pdfUpload())->assertStatus(201);

        $this->assertSame(1, FurnitureRequest::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_multipart_dimension_scientific_notation_is_not_truncated(): void
    {
        $response = $this->multipart([
            ...self::FIELDS,
            'dimensions' => ['length' => '25e-1', 'unit' => 'cm'],
        ])->assertStatus(201);

        $response->assertJsonPath('data.dimensions.length', 2.5);
        $response->assertJsonPath('data.dimensions.unit', 'cm');

        $this->assertSame(2.5, FurnitureRequest::query()->sole()->dimensions['length']);
    }

    public function test_multipart_accepts_the_documented_bracket_dimension_encoding(): void
    {
        parse_str('dimensions[length]=220&dimensions[width]=90&dimensions[unit]=cm', $dimensions);

        $response = $this->multipart([...self::FIELDS, ...$dimensions])->assertStatus(201);

        $response->assertJsonPath('data.dimensions.length', 220);
        $response->assertJsonPath('data.dimensions.width', 90);
        $response->assertJsonPath('data.dimensions.unit', 'cm');

        $stored = FurnitureRequest::query()->sole()->dimensions;
        $this->assertSame(220, $stored['length']);
        $this->assertSame('cm', $stored['unit']);
    }

    public function test_unknown_uploaded_file_field_is_rejected(): void
    {
        RateLimiter::for('anonymous-submit', static fn (): Limit => Limit::none());

        $this->post(
            self::URL,
            [...self::FIELDS, 'reference_file' => $this->pngUpload('stray.png')],
            ['Content-Type' => 'multipart/form-data; boundary=----test'],
        )
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
            ->assertJsonPath('errors.0.field', 'reference_file');

        $this->assertDatabaseCount('furniture_requests', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    /** @param array<string, mixed> $fields */
    private function multipart(array $fields, mixed $attachment = null): TestResponse
    {
        RateLimiter::for('anonymous-submit', static fn (): Limit => Limit::none());

        $payload = $fields;

        if ($attachment !== null) {
            $payload['attachment'] = $attachment;
        }

        return $this->post(self::URL, $payload, ['Content-Type' => 'multipart/form-data; boundary=----test']);
    }

    private function failingDisk(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once();
        Storage::set('attachments', $disk);
    }
}
