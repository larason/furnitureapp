<?php

namespace App\Services\Attachments;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Private attachment storage on the configured Laravel disk. It owns key
 * generation and physical file writes/deletes only; it performs no
 * authorization. Storage keys are server-generated and never derived from
 * client input.
 */
final class AttachmentStorage
{
    public function store(ValidatedAttachment $file, string $directory): string
    {
        $key = $directory.'/'.strtolower((string) Str::ulid()).'.'.$file->extension;
        $stream = fopen($file->path, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Unable to read the uploaded attachment.');
        }

        try {
            $written = $this->disk()->writeStream($key, $stream);
        } catch (Throwable $exception) {
            $this->delete($this->diskName(), $key);

            throw new \RuntimeException('Unable to store the uploaded attachment.', 0, $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            $this->delete($this->diskName(), $key);

            throw new \RuntimeException('Unable to store the uploaded attachment.');
        }

        return $key;
    }

    public function delete(?string $disk, ?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        try {
            if (Storage::disk($disk ?? $this->diskName())->delete($key) === false) {
                throw new \RuntimeException('Attachment cleanup failed.');
            }
        } catch (Throwable $exception) {
            Log::warning('attachment.cleanup_failed', [
                'disk' => $disk,
                'exception' => $exception::class,
            ]);
        }
    }

    public function deleteOrFail(?string $disk, ?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        if (Storage::disk($disk ?? $this->diskName())->delete($key) === false) {
            throw new \RuntimeException('Attachment cleanup failed.');
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function diskName(): string
    {
        return (string) config('attachments.disk');
    }
}
