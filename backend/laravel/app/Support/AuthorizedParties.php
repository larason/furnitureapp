<?php

namespace App\Support;

/**
 * Origin allow-list rules for Clerk session tokens and CORS.
 *
 * Clerk asserts the token's `azp` claim, and this application compares it with
 * `array_intersect`, so membership is an exact string match. A trailing slash,
 * a `localhost`/`127.0.0.1` alias, a scheme change or a port change are all
 * different origins. This mirrors `OfficialClerkTokenVerifier` so an operator
 * can be told which origin is missing before a sign-in is rejected at runtime.
 */
final class AuthorizedParties
{
    /** @param list<string> $authorizedParties */
    public static function allows(array $authorizedParties, string $origin): bool
    {
        if (trim($origin) === '') {
            return false;
        }

        foreach ($authorizedParties as $authorized) {
            if ($authorized === $origin) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $clientOrigins
     * @param  list<string>  $authorizedParties
     * @return list<string>
     */
    public static function missingFor(array $clientOrigins, array $authorizedParties): array
    {
        $missing = [];

        foreach ($clientOrigins as $origin) {
            if (trim($origin) === '') {
                continue;
            }

            if (! self::allows($authorizedParties, $origin)) {
                $missing[] = $origin;
            }
        }

        return array_values(array_unique($missing));
    }
}
