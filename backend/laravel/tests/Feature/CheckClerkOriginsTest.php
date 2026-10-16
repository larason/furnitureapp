<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckClerkOriginsTest extends TestCase
{
    #[Test]
    public function it_reports_success_when_every_client_origin_is_authorized(): void
    {
        Config::set('clerk.authorized_parties', ['http://localhost:3000', 'http://localhost:3001']);
        Config::set('cors.allowed_origins', ['http://localhost:3000', 'http://localhost:3001']);

        $this->artisan('clerk:check-origins', ['origin' => ['http://localhost:3000', 'http://localhost:3001']])
            ->expectsOutputToContain('authorized')
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_and_names_the_origin_missing_from_the_authorized_parties(): void
    {
        Config::set('clerk.authorized_parties', ['http://localhost:3000']);
        Config::set('cors.allowed_origins', ['http://localhost:3000']);

        $this->artisan('clerk:check-origins', ['origin' => ['http://localhost:3001']])
            ->expectsOutputToContain('http://localhost:3001')
            ->expectsOutputToContain('CLERK_AUTHORIZED_PARTIES')
            ->assertFailed();
    }

    #[Test]
    public function it_reports_the_origin_missing_from_cors_even_when_clerk_authorizes_it(): void
    {
        Config::set('clerk.authorized_parties', ['http://localhost:3001']);
        Config::set('cors.allowed_origins', []);

        $this->artisan('clerk:check-origins', ['origin' => ['http://localhost:3001']])
            ->expectsOutputToContain('CORS_ALLOWED_ORIGINS')
            ->assertFailed();
    }

    #[Test]
    public function it_rejects_a_loopback_alias_that_looks_equivalent_but_is_not(): void
    {
        Config::set('clerk.authorized_parties', ['http://127.0.0.1:3001']);
        Config::set('cors.allowed_origins', ['http://127.0.0.1:3001']);

        $this->artisan('clerk:check-origins', ['origin' => ['http://localhost:3001']])
            ->expectsOutputToContain('http://localhost:3001')
            ->assertFailed();
    }

    #[Test]
    public function it_defaults_to_the_admin_origin_plus_the_documented_customer_origin(): void
    {
        Config::set('clerk.authorized_parties', ['http://localhost:3000', 'http://localhost:3001']);
        Config::set('cors.allowed_origins', ['http://localhost:3000', 'http://localhost:3001']);

        $this->artisan('clerk:check-origins')->assertSuccessful();
    }

    #[Test]
    public function the_default_resolution_still_fails_when_only_the_customer_origin_is_authorized(): void
    {
        Config::set('clerk.authorized_parties', ['http://localhost:3000']);
        Config::set('cors.allowed_origins', ['http://localhost:3000']);

        $this->artisan('clerk:check-origins')
            ->expectsOutputToContain('http://localhost:3001')
            ->expectsOutputToContain('CLERK_AUTHORIZED_PARTIES')
            ->assertFailed();
    }
}
