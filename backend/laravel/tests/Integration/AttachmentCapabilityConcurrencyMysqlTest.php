<?php

namespace Tests\Integration;

use App\Exceptions\Api\ApiException;
use App\Models\Attachment;
use App\Models\AttachmentUploadCapability;
use App\Models\FurnitureRequest;
use App\Services\Attachments\UploadAttachment;
use App\Services\Attachments\UploadCapabilityService;
use App\Services\Attachments\ValidatedAttachment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RunsConcurrentWorkers;
use Tests\Support\UsesDisposableMysqlDatabase;
use Tests\TestCase;

/**
 * Real MariaDB/MySQL concurrency verification for the single-use upload
 * capability and the one-attachment-per-parent guard. Not part of the default
 * suite; run explicitly against the disposable DB:
 *
 *   ATTACHMENT_CAPABILITY_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/AttachmentCapabilityConcurrencyMysqlTest.php
 */
class AttachmentCapabilityConcurrencyMysqlTest extends TestCase
{
    use RunsConcurrentWorkers;
    use UsesDisposableMysqlDatabase;

    private const RACES = 10;

    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function databaseConnectionName(): string
    {
        return 'mysql_attachment_capability';
    }

    protected function databaseEnvironmentVariable(): string
    {
        return 'ATTACHMENT_CAPABILITY_MYSQL_TEST_DATABASE';
    }

    public function test_same_capability_produces_one_attachment_and_consumes_once(): void
    {
        $this->withSharedAttachmentDisk(function (string $disk, string $sourceDir): void {
            for ($iteration = 0; $iteration < self::RACES; $iteration++) {
                $request = FurnitureRequest::factory()->create();
                $file = $this->validatedAttachment($sourceDir);
                $token = app(UploadCapabilityService::class)->issueForRequest((int) $request->id);

                $results = $this->runConcurrentWorkers(
                    fn (): string => $this->attemptUpload((int) $request->id, $file, $token),
                    fn (): string => $this->attemptUpload((int) $request->id, $file, $token),
                );

                $this->assertSame(1, count(array_filter($results, fn (string $r): bool => $r === 'created')), "iteration {$iteration}");
                $this->assertSame(1, count(array_filter($results, fn (string $r): bool => $r === 'rejected:INVALID_AUTHENTICATION')), "iteration {$iteration}");

                $capability = AttachmentUploadCapability::query()->where('parent_id', $request->id)->sole();
                $this->assertNotNull($capability->used_at, "iteration {$iteration}");
                $this->assertSame($iteration + 1, AttachmentUploadCapability::query()->count(), "iteration {$iteration}");

                $attachment = Attachment::query()->where('furniture_request_id', $request->id)->sole();
                $this->assertSame((int) $request->id, (int) $attachment->furniture_request_id, "iteration {$iteration}");
                $this->assertSame($iteration + 1, Attachment::query()->count(), "iteration {$iteration}");

                $this->assertCount($iteration + 1, Storage::disk($disk)->allFiles(), "iteration {$iteration}");
            }
        });
    }

    public function test_concurrent_authenticated_uploads_keep_one_attachment(): void
    {
        $this->withSharedAttachmentDisk(function (string $disk, string $sourceDir): void {
            for ($iteration = 0; $iteration < self::RACES; $iteration++) {
                $request = FurnitureRequest::factory()->create();
                $file = $this->validatedAttachment($sourceDir);

                $results = $this->runConcurrentWorkers(
                    fn (): string => $this->attemptUpload((int) $request->id, $file, null),
                    fn (): string => $this->attemptUpload((int) $request->id, $file, null),
                );

                $this->assertSame(1, count(array_filter($results, fn (string $r): bool => $r === 'created')), "iteration {$iteration}");
                $this->assertSame(1, count(array_filter($results, fn (string $r): bool => $r === 'rejected:CONFLICT')), "iteration {$iteration}");
                $this->assertSame(1, Attachment::query()->where('furniture_request_id', $request->id)->count(), "iteration {$iteration}");
                $this->assertSame($iteration + 1, Attachment::query()->count(), "iteration {$iteration}");
                $this->assertCount($iteration + 1, Storage::disk($disk)->allFiles(), "iteration {$iteration}");
            }
        });
    }

    private function attemptUpload(int $requestId, ValidatedAttachment $file, ?string $token): string
    {
        try {
            app(UploadAttachment::class)->forRequest(
                FurnitureRequest::query()->findOrFail($requestId),
                $file,
                $token,
            );

            return 'created';
        } catch (ApiException $exception) {
            return 'rejected:'.$exception->errorCode()->value;
        }
    }

    /**
     * Runs one scenario with a private local disk that is genuinely shared
     * across forked workers (unlike process-local in-memory fakes). Source
     * upload files live outside the disk root so file counts reflect stored
     * attachments only. Both unique directories are removed afterwards.
     *
     * @param  callable(string, string): void  $scenario
     */
    private function withSharedAttachmentDisk(callable $scenario): void
    {
        $disk = 'attachment_race';
        $root = sys_get_temp_dir().'/furniture-attachment-disk-'.bin2hex(random_bytes(8));
        $sourceDir = sys_get_temp_dir().'/furniture-attachment-src-'.bin2hex(random_bytes(8));

        File::makeDirectory($root, 0777, true);
        File::makeDirectory($sourceDir, 0777, true);
        config(['filesystems.disks.'.$disk => [
            'driver' => 'local',
            'root' => $root,
            'throw' => false,
        ]]);
        config(['attachments.disk' => $disk]);
        Storage::purge($disk);

        try {
            $scenario($disk, $sourceDir);
        } finally {
            Storage::purge($disk);
            File::deleteDirectory($root);
            File::deleteDirectory($sourceDir);
        }
    }

    private function validatedAttachment(string $sourceDir): ValidatedAttachment
    {
        $path = $sourceDir.'/source-'.bin2hex(random_bytes(6)).'.png';
        file_put_contents($path, (string) base64_decode(self::TINY_PNG, true));

        return new ValidatedAttachment($path, 'reference.png', 'image/png', (int) filesize($path), 'png');
    }
}
