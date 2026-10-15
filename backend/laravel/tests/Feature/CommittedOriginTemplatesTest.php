<?php

namespace Tests\Feature;

use App\Support\AuthorizedParties;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The uncommitted Laravel `.env` is invisible to the suite, so this guards the
 * committed templates: a new frontend origin must be documented in the Laravel
 * example before an operator can copy it and still authorize that origin.
 */
class CommittedOriginTemplatesTest extends TestCase
{
    #[Test]
    #[DataProvider('originListProvider')]
    public function the_laravel_example_authorizes_every_frontend_origin(string $variable): void
    {
        $missing = AuthorizedParties::missingFor(
            $this->declaredFrontendOrigins(),
            $this->exampleList($variable),
        );

        self::assertSame([], $missing, "{$variable} in .env.example must cover every declared frontend origin.");
    }

    /** @return iterable<string, array{string}> */
    public static function originListProvider(): iterable
    {
        // Clerk authorized parties gate the token itself: an unlisted `azp` is
        // rejected as INVALID_AUTHENTICATION, which the admin surfaces as
        // "Session not accepted". CORS only governs browser-originated calls and
        // does not affect the server-to-server admission request, so the admin
        // origin must be authorized in its own right, not merely cross-origin-capable.
        yield 'Clerk authorized parties' => ['CLERK_AUTHORIZED_PARTIES'];
        yield 'CORS allowed origins' => ['CORS_ALLOWED_ORIGINS'];
    }

    #[Test]
    public function the_admin_example_declares_an_origin(): void
    {
        self::assertNotSame('', $this->exampleValue('frontend/admin', 'NEXT_PUBLIC_ADMIN_ORIGIN'));
    }

    /** @return list<string> */
    private function declaredFrontendOrigins(): array
    {
        $origins = ['http://localhost:3000'];
        $admin = $this->exampleValue('frontend/admin', 'NEXT_PUBLIC_ADMIN_ORIGIN');

        if ($admin !== '') {
            $origins[] = $admin;
        }

        return $origins;
    }

    /** @return list<string> */
    private function exampleList(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->exampleValue('backend/laravel', $key)))));
    }

    private function exampleValue(string $app, string $key): string
    {
        $path = dirname(base_path(), 2).'/'.$app.'/.env.example';
        self::assertFileExists($path);

        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            $line = trim($line);

            if (! str_starts_with($line, $key.'=')) {
                continue;
            }

            return trim(explode('=', $line, 2)[1], " \t\"'");
        }

        return '';
    }
}
