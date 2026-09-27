<?php

namespace Tests\Unit;

use App\Support\ProductionConfiguration;
use RuntimeException;
use Tests\TestCase;

class ProductionConfigurationTest extends TestCase
{
    public function test_production_requires_clerk_restrictions(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config(['clerk.issuer' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CLERK_ISSUER');

        ProductionConfiguration::validate();
    }

    public function test_safe_local_service_production_configuration_passes(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();

        ProductionConfiguration::validate();
        $this->addToAssertionCount(1);
    }

    public function test_remote_mysql_requires_a_certificate_authority(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => 'db.example.test',
            'database.connections.mysql.unix_socket' => '',
            'database.connections.mysql.options' => [],
        ]);

        $this->expectExceptionMessage('MYSQL_ATTR_SSL_CA');
        ProductionConfiguration::validate();
    }

    public function test_mysql_url_host_overrides_the_fallback_local_host_for_tls_validation(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.url' => 'mysql://user:password@remote-db.example.test:3306/furniture',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.unix_socket' => '',
            'database.connections.mysql.options' => [],
        ]);

        $this->expectExceptionMessage('MYSQL_ATTR_SSL_CA');
        ProductionConfiguration::validate();
    }

    public function test_remote_redis_requires_tls(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config([
            'database.redis.default.host' => 'redis.example.test',
            'database.redis.default.url' => 'redis://redis.example.test',
        ]);

        $this->expectExceptionMessage('REDIS_URL=rediss://');
        ProductionConfiguration::validate();
    }

    public function test_non_array_redis_configuration_fails_cleanly(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config(['database.redis.default' => 'rediss://remote-redis.example.test']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('REDIS_URL=rediss://');
        ProductionConfiguration::validate();
    }

    public function test_url_only_remote_redis_does_not_use_the_fallback_local_host(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config([
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.url' => 'redis://remote-redis.example.test:6379',
        ]);

        $this->expectExceptionMessage('REDIS_URL=rediss://');
        ProductionConfiguration::validate();
    }

    public function test_url_only_remote_redis_accepts_tls(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config([
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.url' => 'rediss://remote-redis.example.test:6380',
        ]);

        ProductionConfiguration::validate();
        $this->addToAssertionCount(1);
    }

    public function test_remote_smtp_requires_a_secure_scheme(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->safeProductionConfig();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.url' => null,
        ]);

        $this->expectExceptionMessage('secure MAIL_SCHEME or MAIL_URL');
        ProductionConfiguration::validate();
    }

    private function safeProductionConfig(): void
    {
        config([
            'app.url' => 'https://api.example.test',
            'app.debug' => false,
            'cart.guest_cookie_secure' => true,
            'clerk.issuer' => 'https://clerk.example.test',
            'clerk.jwt_key' => 'test-public-key',
            'clerk.audiences' => ['furniture-api'],
            'clerk.authorized_parties' => ['https://shop.example.test'],
            'database.default' => 'sqlite',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.url' => null,
            'mail.default' => 'log',
            'security.trusted_proxies' => ['10.0.0.0/8'],
            'session.secure' => true,
        ]);
    }
}
