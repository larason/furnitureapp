<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait UsesDisposableMysqlDatabase
{
    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private string $previousDefaultConnection = '';

    abstract protected function databaseEnvironmentVariable(): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');
        $database = (string) getenv($this->databaseEnvironmentVariable());

        if ($database !== self::DISPOSABLE_DATABASE) {
            $this->markTestSkipped('requires disposable MySQL/MariaDB integration database (set '.$this->databaseEnvironmentVariable().'='.self::DISPOSABLE_DATABASE.').');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl and posix extensions required.');
        }

        if (app()->environment('production')) {
            $this->fail('Refusing to run destructive concurrency tests in production.');
        }

        config(['database.connections.'.$this->databaseConnectionName() => array_merge(
            config('database.connections.mysql'),
            ['database' => $database],
        )]);
        config(['database.default' => $this->databaseConnectionName()]);

        DB::purge($this->databaseConnectionName());
        Artisan::call('migrate:fresh', ['--database' => $this->databaseConnectionName(), '--force' => true]);
        $this->seedDisposableMysqlDatabase();
    }

    protected function tearDown(): void
    {
        DB::purge($this->databaseConnectionName());
        config(['database.default' => $this->previousDefaultConnection]);

        parent::tearDown();
    }

    protected function seedDisposableMysqlDatabase(): void {}
}
