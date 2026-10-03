<?php

namespace Tests\Feature;

use App\Exceptions\AttachmentCleanupRequired;
use App\Services\Attachments\AttachmentStorage;
use App\Services\Attachments\ValidatedAttachment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AttachmentStorageTest extends TestCase
{
    private ?string $tempPath = null;

    protected function tearDown(): void
    {
        if ($this->tempPath !== null && is_file($this->tempPath)) {
            unlink($this->tempPath);
        }

        parent::tearDown();
    }

    public function test_a_successful_write_returns_the_generated_key(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once()->andReturn(true);
        $disk->shouldNotReceive('delete');
        Storage::set('attachments', $disk);

        $key = $this->storage()->store($this->attachment(), 'attachments/requests');

        $this->assertMatchesRegularExpression('#^attachments/requests/[0-9a-z]+\.png$#', $key);
    }

    public function test_a_failed_write_that_returns_false_compensates_and_throws(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once();
        Storage::set('attachments', $disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to store the uploaded attachment.');

        $this->storage()->store($this->attachment(), 'attachments/enquiries');
    }

    public function test_a_thrown_write_failure_compensates_and_throws_safely(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once()->andThrow(new RuntimeException('disk path /srv/secret leaked'));
        $disk->shouldReceive('delete')->once();
        Storage::set('attachments', $disk);

        try {
            $this->storage()->store($this->attachment(), 'attachments/requests');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to store the uploaded attachment.', $exception->getMessage());
            $this->assertStringNotContainsString('/srv/secret', $exception->getMessage());

            return;
        }

        $this->fail('Expected a safe storage exception.');
    }

    public function test_a_failed_compensation_delete_surfaces_cleanup_required_with_the_key(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('attachments', $disk);

        try {
            $this->storage()->store($this->attachment(), 'attachments/requests');
        } catch (AttachmentCleanupRequired $exception) {
            $this->assertSame('attachments', $exception->storageDisk);
            $this->assertMatchesRegularExpression('#^attachments/requests/[0-9a-z]+\.png$#', $exception->storageKey);

            return;
        }

        $this->fail('Expected AttachmentCleanupRequired to retain the generated key.');
    }

    public function test_a_failed_cleanup_delete_is_logged_and_surfaces_cleanup_required(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('attachment.cleanup_failed', Mockery::type('array'));

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('attachments', $disk);

        try {
            $this->storage()->delete('attachments', 'attachments/requests/orphan.png');
        } catch (AttachmentCleanupRequired $exception) {
            $this->assertSame('attachments', $exception->storageDisk);
            $this->assertSame('attachments/requests/orphan.png', $exception->storageKey);

            return;
        }

        $this->fail('Expected a failed cleanup delete to surface AttachmentCleanupRequired.');
    }

    public function test_a_successful_cleanup_delete_reports_no_failure(): void
    {
        Log::shouldReceive('warning')->never();

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::set('attachments', $disk);

        $this->storage()->delete('attachments', 'attachments/requests/gone.png');
    }

    private function storage(): AttachmentStorage
    {
        return $this->app->make(AttachmentStorage::class);
    }

    private function attachment(): ValidatedAttachment
    {
        $this->tempPath = (string) tempnam(sys_get_temp_dir(), 'attachment');
        file_put_contents($this->tempPath, 'content');

        return new ValidatedAttachment(
            path: $this->tempPath,
            filename: 'reference.png',
            contentType: 'image/png',
            size: 7,
            extension: 'png',
        );
    }
}
