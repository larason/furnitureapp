<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Backend Quality Baseline (Phase 2.10)

Coding standard: Laravel preset via Pint (`pint.json`). Static analysis: Larastan/PHPStan level 5 (`phpstan.neon`) on `app`, `bootstrap/app.php`, `config`, `routes`, `database`.

```bash
composer format        # fix formatting (Pint)
composer format:check  # verify formatting without changes
composer analyse       # static analysis (PHPStan)
composer test          # existing backend tests
```

Workflow: `format → format:check → analyse → test` (later phases add CI). No broad suppressions; baseline is clean at level 5.

## Testing (Phase 2.11)

Framework: PHPUnit 12 (via `phpunit.xml`) — `tests/Unit` for pure logic, `tests/Feature` for HTTP/API. Test env is isolated (`APP_ENV=testing`, `DB_CONNECTION=sqlite :memory:`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`) and does not use production credentials. Database-backed tests use `RefreshDatabase` (migrations run per test, no cross-test contamination) — ready for Group C schemas.

```bash
composer test                                          # full suite
php artisan test --filter=HealthEndpointTest             # one class
php artisan test --filter=test_health_endpoint_returns_ok_status  # one test
php artisan test tests/Feature/ApiErrorHandlingTest.php  # one file
```

`composer test` and focused runs return non-zero on failure (verified). No speculative domain factories/fixtures; only `UserFactory` from the skeleton is present.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
