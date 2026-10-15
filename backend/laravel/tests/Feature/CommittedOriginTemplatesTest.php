<?php

namespace Tests\Feature;

use App\Support\AuthorizedParties;
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
    public function the_laravel_example_documents_every_frontend_origin(): void
    {
        $missing = AuthorizedParties::missingFor(
            $this->declaredFrontendOrigins(),
            $this->exampleList('CORS_ALLOWED_ORIGINS'),
        );

        self::assertSame([], $missing, 'CORS_ALLOWED_ORIGINS in .env.example must cover every declared frontend origin.');
    }

    #[Test]
    public function the_admin_example_declares_an_origin(): void
    {
        self::assertNotSame('', $this->exampleValue('frontend/admin', 'NEXT_PUBLIC_ADMIN_ORIGIN'));
    }

    #[Test]
    public function the_laravel_example_explains_the_authorized_parties_rule(): void
    {
        $example = $this->exampleValue('backend/laravel', 'CLERK_AUTHORIZED_PARTIES');

        self::assertNotSame('', $example, 'CLERK_AUTHORIZED_PARTIES must remain in .env.example.');
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
