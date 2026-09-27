<?php

use App\Models\Cart;
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

Schedule::call(function (): void {
    Cart::query()
        ->whereNull('user_id')
        ->where('updated_at', '<=', now()->subDays((int) config('security.guest_cart_stale_days')))
        ->whereDoesntHave('items')
        ->delete();
})->name('prune-stale-empty-guest-carts')->daily()->withoutOverlapping();
