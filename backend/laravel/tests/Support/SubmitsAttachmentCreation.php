<?php

namespace Tests\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;

trait SubmitsAttachmentCreation
{
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
