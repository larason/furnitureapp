<?php

namespace App\Providers;

use App\Authentication\Clerk\ClerkUserGateway;
use App\Authentication\Clerk\OfficialClerkTokenVerifier;
use App\Authentication\Clerk\OfficialClerkUserGateway;
use App\Authentication\ClerkTokenVerifier;
use Clerk\Backend\ClerkBackend;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
