<?php

namespace App\Console\Commands;

use App\Support\AuthorizedParties;
use Illuminate\Console\Command;

final class CheckClerkOrigins extends Command
{
    private const ADMIN_ORIGIN_VARIABLE = 'NEXT_PUBLIC_ADMIN_ORIGIN';

    protected $signature = 'clerk:check-origins
        {origin?* : Client origins to verify; defaults to the origins declared by the frontends}';

    protected $description = 'Verify that every frontend origin is an authorized Clerk party and an allowed CORS origin';

    public function handle(): int
    {
        /** @var list<string> $requested */
        $requested = array_values(array_filter(array_map('trim', (array) $this->argument('origin'))));
        $origins = $requested === [] ? $this->declaredClientOrigins() : $requested;

        if ($origins === []) {
            $this->error('No client origins were resolved. Pass them explicitly, e.g. '.self::ADMIN_ORIGIN_VARIABLE.'=http://localhost:3001');

            return self::FAILURE;
        }

        $problems = [];

        foreach ([
            'CLERK_AUTHORIZED_PARTIES' => $this->configuredList('clerk.authorized_parties'),
            'CORS_ALLOWED_ORIGINS' => $this->configuredList('cors.allowed_origins'),
        ] as $variable => $configured) {
            $missing = AuthorizedParties::missingFor($origins, $configured);

            if ($missing === []) {
                $this->line("{$variable}: every client origin is authorized.");

                continue;
            }

            $problems[] = $variable;
            $this->error("{$variable} is missing: ".implode(', ', $missing));
        }

        if ($problems !== []) {
            $this->warn('Add the missing origins to '.implode(' and ', $problems).' in your Laravel .env, then run `php artisan config:clear`.');
            $this->warn('Clerk compares the token `azp` claim exactly: a trailing slash, a localhost/127.0.0.1 alias, a scheme change or a port change is a different origin.');

            return self::FAILURE;
        }

        $this->info('All client origins are authorized.');

        return self::SUCCESS;
    }

    /**
     * The admin application declares its own origin; the customer website has no
     * explicit origin variable, so its documented local origin is used.
     *
     * @return list<string>
     */
    private function declaredClientOrigins(): array
    {
        return array_values(array_unique(array_filter([
            $this->adminDeclaredOrigin(),
            'http://localhost:3000',
        ])));
    }

    private function adminDeclaredOrigin(): ?string
    {
        $admin = dirname(base_path(), 2).'/frontend/admin';

        // `.env.example` is committed, so it is the authoritative fallback: on a
        // fresh checkout or in CI the local env files are absent and the command
        // must still check the documented admin origin rather than silently
        // narrowing the set to the customer origin.
        foreach (['.env.local', '.env', '.env.example'] as $file) {
            $path = $admin.'/'.$file;

            if (! is_readable($path)) {
                continue;
            }

            $value = $this->readEnvValue((string) file_get_contents($path), self::ADMIN_ORIGIN_VARIABLE);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function readEnvValue(string $contents, string $key): ?string
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if (! str_starts_with($line, $key.'=')) {
                continue;
            }

            $value = trim(explode('=', $line, 2)[1], " \t\"'");

            return $value === '' ? null : $value;
        }

        return null;
    }

    /** @return list<string> */
    private function configuredList(string $key): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && trim($item) !== ''));
    }
}
