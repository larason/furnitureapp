<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    IdempotencyKey::query()->where('expires_at', '<=', now())->delete();
})->name('prune-expired-idempotency-keys')->hourly()->withoutOverlapping();

// Safety net: retries pending private-attachment cleanup tasks even when the
// post-commit job dispatch fails or the queue is unavailable.
Schedule::command('attachments:cleanup')->everyMinute()->withoutOverlapping();

Schedule::command('attachments:prune-upload-capabilities')->hourly()->withoutOverlapping();
