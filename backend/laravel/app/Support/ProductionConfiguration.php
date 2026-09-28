<?php

namespace App\Support;

use Illuminate\Database\ConfigurationUrlParser;
use InvalidArgumentException;
use Pdo\Mysql;
use RuntimeException;

final class ProductionConfiguration
{
    private const SMTP_REQUIREMENT = 'secure MAIL_SCHEME or MAIL_URL';

    private const SMTP_PEER_VERIFICATION = 'MAIL verify_peer';

    private const SMTP_SUPPORTED_SCHEMES = ['smtp', 'smtps'];

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
        $options = is_array($database['options'] ?? null) ? $database['options'] : [];

        if (! in_array($driver, ['mysql', 'mariadb'], true)
            || ! self::isRemoteHost((string) ($database['host'] ?? ''))
            || ! empty($database['unix_socket'])) {
            return;
        }

        if (empty($options[Mysql::ATTR_SSL_CA] ?? null)) {
            $missing[] = 'MYSQL_ATTR_SSL_CA';
        }

        if (($options[Mysql::ATTR_SSL_VERIFY_SERVER_CERT] ?? null) === false) {
            $missing[] = 'MYSQL_ATTR_SSL_VERIFY_SERVER_CERT';
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
        foreach (self::smtpMailerNames((string) config('mail.default')) as $name) {
            self::validateSmtpMailer($missing, config('mail.mailers.'.$name));
        }
    }

    /**
     * Resolves the SMTP mailers reachable from the default mailer, expanding
     * `failover` and `roundrobin` member lists (including nested ones). A
     * visited set guards against cyclic member references.
     *
     * @param  list<string>  $visited
     * @return list<string>
     */
    private static function smtpMailerNames(string $name, array $visited = []): array
    {
        if (in_array($name, $visited, true)) {
            return [];
        }

        $visited[] = $name;
        $config = config('mail.mailers.'.$name);
        $transport = is_array($config) ? ($config['transport'] ?? null) : null;

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $names = [];

            foreach ((array) ($config['mailers'] ?? []) as $member) {
                if (is_string($member)) {
                    $names = array_merge($names, self::smtpMailerNames($member, $visited));
                }
            }

            return array_values(array_unique($names));
        }

        return $transport === 'smtp' || $name === 'smtp' ? [$name] : [];
    }

    private static function validateSmtpMailer(array &$missing, mixed $smtp): void
    {
        if (! is_array($smtp)) {
            $missing[] = self::SMTP_REQUIREMENT;

            return;
        }

        try {
            $transport = (new ConfigurationUrlParser)->parseConfiguration($smtp);
        } catch (InvalidArgumentException) {
            $missing[] = self::SMTP_REQUIREMENT;

            return;
        }

        foreach (self::smtpSecurityProblems($smtp, $transport) as $problem) {
            $missing[] = $problem;
        }
    }

    /**
     * Inspects the effective SMTP transport configuration (including options
     * merged from `MAIL_URL`) so remote mailers cannot silently disable
     * encryption or peer verification.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $transport
     * @return list<string>
     */
    private static function smtpSecurityProblems(array $raw, array $transport): array
    {
        $url = (string) ($raw['url'] ?? '');
        $host = (string) ($transport['host'] ?? '');
        $scheme = self::resolveSmtpScheme($transport, $url);
        $problems = [];

        if ($url !== '' && $host === '') {
            $problems[] = self::SMTP_REQUIREMENT;
        } elseif (self::isRemoteHost($host)) {
            $tlsEnforced = self::isTruthy($transport['require_tls'] ?? false);

            if (! in_array($scheme, self::SMTP_SUPPORTED_SCHEMES, true)
                || ($scheme === 'smtp' && ! $tlsEnforced)) {
                $problems[] = self::SMTP_REQUIREMENT;
            }

            if (self::disablesPeerVerification($transport)) {
                $problems[] = self::SMTP_PEER_VERIFICATION;
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $transport
     */
    private static function resolveSmtpScheme(array $transport, string $url): string
    {
        $scheme = $url !== ''
            ? (string) parse_url($url, PHP_URL_SCHEME)
            : (string) ($transport['scheme'] ?? '');

        if ($scheme === '') {
            $scheme = (int) ($transport['port'] ?? 0) === 465 ? 'smtps' : 'smtp';
        }

        return strtolower($scheme);
    }

    /**
     * @param  array<string, mixed>  $transport
     */
    private static function disablesPeerVerification(array $transport): bool
    {
        $verifyPeer = $transport['verify_peer'] ?? null;

        return $verifyPeer !== null
            && $verifyPeer !== ''
            && ! self::isTruthy($verifyPeer);
    }

    private static function isTruthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) === true;
    }

    private static function isRemoteHost(string $host): bool
    {
        return ! in_array(strtolower($host), ['', 'localhost', '127.0.0.1', '::1'], true);
    }
}
