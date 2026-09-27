<?php

namespace App\Support;

use Illuminate\Database\ConfigurationUrlParser;
use InvalidArgumentException;
use Pdo\Mysql;
use RuntimeException;

final class ProductionConfiguration
{
    public static function validate(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $missing = [];
        self::requireValue($missing, 'CLERK_ISSUER', config('clerk.issuer'));
        self::requireList($missing, 'CLERK_AUDIENCES', config('clerk.audiences'));
        self::requireList($missing, 'CLERK_AUTHORIZED_PARTIES', config('clerk.authorized_parties'));
        self::requireList($missing, 'TRUSTED_PROXIES', config('security.trusted_proxies'));

        if (blank(config('clerk.secret_key')) && blank(config('clerk.jwt_key'))) {
            $missing[] = 'CLERK_SECRET_KEY or CLERK_JWT_KEY';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $missing[] = 'APP_URL=https://...';
        }

        if ((bool) config('app.debug')) {
            $missing[] = 'APP_DEBUG=false';
        }

        if (! (bool) config('session.secure') || ! (bool) config('cart.guest_cookie_secure')) {
            $missing[] = 'secure cookie settings';
        }

        self::validateDatabaseTls($missing);
        self::validateRedisTls($missing);
        self::validateSmtpTls($missing);

        if ($missing !== []) {
            throw new RuntimeException('Unsafe production configuration: '.implode(', ', $missing).'.');
        }
    }

    private static function requireValue(array &$missing, string $name, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            $missing[] = $name;
        }
    }

    private static function requireList(array &$missing, string $name, mixed $value): void
    {
        if (! is_array($value) || $value === []) {
            $missing[] = $name;
        }
    }

    private static function validateDatabaseTls(array &$missing): void
    {
        $connection = (string) config('database.default');
        $database = config('database.connections.'.$connection, []);

        if (! is_array($database)) {
            $missing[] = 'valid DB_URL';

            return;
        }

        try {
            $database = (new ConfigurationUrlParser)->parseConfiguration($database);
        } catch (InvalidArgumentException) {
            $missing[] = 'valid DB_URL';

            return;
        }

        $driver = (string) ($database['driver'] ?? $connection);

        if (in_array($driver, ['mysql', 'mariadb'], true)
            && self::isRemoteHost((string) ($database['host'] ?? ''))
            && empty($database['unix_socket'])
            && empty($database['options'][Mysql::ATTR_SSL_CA] ?? null)) {
            $missing[] = 'MYSQL_ATTR_SSL_CA';
        }
    }

    private static function validateRedisTls(array &$missing): void
    {
        $redis = config('database.redis.default', []);

        if (! is_array($redis)) {
            $missing[] = 'REDIS_URL=rediss://...';

            return;
        }

        $url = (string) ($redis['url'] ?? '');
        $host = $url !== ''
            ? (string) (parse_url($url, PHP_URL_HOST) ?? '')
            : (string) ($redis['host'] ?? '');
        $scheme = $url !== '' ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';

        if (($url !== '' && $host === '') || (self::isRemoteHost($host) && $scheme !== 'rediss')) {
            $missing[] = 'REDIS_URL=rediss://...';
        }
    }

    private static function validateSmtpTls(array &$missing): void
    {
        if (config('mail.default') !== 'smtp') {
            return;
        }

        $smtp = config('mail.mailers.smtp', []);
        $scheme = strtolower((string) ($smtp['scheme'] ?? ''));
        $url = strtolower((string) ($smtp['url'] ?? ''));

        if (self::isRemoteHost((string) ($smtp['host'] ?? ''))
            && ! in_array($scheme, ['tls', 'ssl', 'smtps'], true)
            && ! str_starts_with($url, 'smtps://')) {
            $missing[] = 'secure MAIL_SCHEME or MAIL_URL';
        }
    }

    private static function isRemoteHost(string $host): bool
    {
        return ! in_array(strtolower($host), ['', 'localhost', '127.0.0.1', '::1'], true);
    }
}
