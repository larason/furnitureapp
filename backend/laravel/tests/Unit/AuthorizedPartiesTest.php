<?php

namespace Tests\Unit;

use App\Support\AuthorizedParties;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AuthorizedPartiesTest extends TestCase
{
    /** @param list<string> $authorized */
    #[Test]
    #[DataProvider('authorizedOriginProvider')]
    public function an_exactly_matching_origin_is_allowed(array $authorized, string $origin, bool $expected): void
    {
        self::assertSame($expected, AuthorizedParties::allows($authorized, $origin));
    }

    /** @return iterable<string, array{list<string>, string, bool}> */
    public static function authorizedOriginProvider(): iterable
    {
        yield 'exact match' => [['http://localhost:3001'], 'http://localhost:3001', true];
        yield 'one of several' => [['http://localhost:3000', 'http://localhost:3001'], 'http://localhost:3001', true];
        yield 'trailing slash is a different origin' => [['http://localhost:3001'], 'http://localhost:3001/', false];
        yield 'loopback alias is a different origin' => [['http://127.0.0.1:3001'], 'http://localhost:3001', false];
        yield 'scheme is significant' => [['https://localhost:3001'], 'http://localhost:3001', false];
        yield 'port is significant' => [['http://localhost:3001'], 'http://localhost:3000', false];
        yield 'empty allow list authorizes nothing' => [[], 'http://localhost:3001', false];
        yield 'wildcard is not a match' => [['*'], 'http://localhost:3001', false];
        yield 'prefix is not a match' => [['http://localhost:300'], 'http://localhost:3001', false];
    }

    /** @param list<string> $authorized */
    #[Test]
    public function every_unauthorized_client_origin_is_reported(): void
    {
        $missing = AuthorizedParties::missingFor(
            ['http://localhost:3000', 'http://localhost:3001'],
            ['http://localhost:3000'],
        );

        self::assertSame(['http://localhost:3001'], $missing);
    }

    /** @param list<string> $authorized */
    #[Test]
    public function no_client_origins_reports_nothing_missing(): void
    {
        self::assertSame([], AuthorizedParties::missingFor([], ['http://localhost:3001']));
    }

    /** @param list<string> $authorized */
    #[Test]
    public function an_empty_allow_list_reports_every_client_origin_as_missing(): void
    {
        self::assertSame(
            ['http://localhost:3000', 'http://localhost:3001'],
            AuthorizedParties::missingFor(['http://localhost:3000', 'http://localhost:3001'], []),
        );
    }

    #[Test]
    public function blank_values_are_ignored_rather_than_matched(): void
    {
        self::assertFalse(AuthorizedParties::allows(['', '   '], ''));
        self::assertFalse(AuthorizedParties::allows(['', '   '], 'http://localhost:3001'));
    }
}
