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
