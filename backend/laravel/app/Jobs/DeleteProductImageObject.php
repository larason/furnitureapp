<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Durable retry for a product image object whose inline deletion failed after
 * the record was removed. The disk name and object key are carried explicitly so
 * a later configuration change cannot redirect the deletion. Exhausted retries
 * are recorded in `failed_jobs` and logged rather than lost.
 */
final class DeleteProductImageObject implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $disk,
        public readonly string $objectKey,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(): void
    {
        if (Storage::disk($this->disk)->delete($this->objectKey) === false) {
            throw new RuntimeException('The product image object could not be deleted.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('product_image.object_delete_failed', [
            'disk' => $this->disk,
            'object_key' => $this->objectKey,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
