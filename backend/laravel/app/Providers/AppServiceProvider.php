<?php

namespace App\Providers;

use App\Authentication\Clerk\ClerkSessionGateway;
use App\Authentication\Clerk\ClerkUserGateway;
use App\Authentication\Clerk\OfficialClerkSessionGateway;
use App\Authentication\Clerk\OfficialClerkTokenVerifier;
use App\Authentication\Clerk\OfficialClerkUserGateway;
use App\Authentication\ClerkTokenVerifier;
use Clerk\Backend\ClerkBackend;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClerkBackend::class, fn (): ClerkBackend => ClerkBackend::builder()
            ->setSecurity((string) config('clerk.secret_key'))
            ->build());
        $this->app->bind(ClerkTokenVerifier::class, OfficialClerkTokenVerifier::class);
        $this->app->bind(ClerkUserGateway::class, OfficialClerkUserGateway::class);
        $this->app->bind(ClerkSessionGateway::class, OfficialClerkSessionGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(100)->by($request->ip()));
        RateLimiter::for('authenticated-read', fn (Request $request) => Limit::perMinute((int) config('rate_limits.authenticated_read_per_minute', 180))->by($this->userKey($request)));
        RateLimiter::for('authenticated-write', fn (Request $request) => Limit::perMinute((int) config('rate_limits.authenticated_write_per_minute', 60))->by($this->userKey($request)));
        RateLimiter::for('anonymous-submit', fn (Request $request) => $request->user() === null
            ? Limit::perMinute(3)->by('ip:'.$request->ip())
            : Limit::perMinute(10)->by($this->userKey($request)));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(5)->by($this->userKey($request)));
        RateLimiter::for('operational-write', fn (Request $request) => Limit::perMinute(120)->by($this->userKey($request)));
        RateLimiter::for('sensitive-action', fn (Request $request) => Limit::perMinute(30)->by($this->userKey($request)));
        RateLimiter::for('cart-add', fn (Request $request) => Limit::perMinute(30)->by($this->userKey($request)));
        RateLimiter::for('order-cancel', fn (Request $request) => Limit::perMinute(5)->by($this->userKey($request)));
        RateLimiter::for('inventory-adjust', fn (Request $request) => Limit::perMinute(20)->by($this->userKey($request)));
        RateLimiter::for('admin-staff', fn (Request $request) => Limit::perMinute(30)->by($this->userKey($request)));
    }

    private function userKey(Request $request): string
    {
        $user = $request->user();

        return $user === null
            ? 'ip:'.$request->ip()
            : 'user:'.(string) $user->getAuthIdentifier();
    }
}
