<?php

namespace App\Authentication\Clerk;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequest;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequestOptions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use RuntimeException;

final class OfficialClerkTokenVerifier implements ClerkTokenVerifier
{
    public function verify(Request $request): AuthenticatedClerkIdentity
    {
        $audiences = $this->nullableList(config('clerk.audiences'));
        $authorizedParties = $this->nullableList(config('clerk.authorized_parties'));

        try {
            $state = AuthenticateRequest::authenticateRequest($request, new AuthenticateRequestOptions(
                secretKey: config('clerk.secret_key'),
                jwtKey: config('clerk.jwt_key'),
                audiences: $audiences,
                authorizedParties: $authorizedParties,
                acceptsToken: ['session_token'],
            ));
        } catch (\Throwable $exception) {
            report($exception);

            throw new AuthenticationException('The Clerk credential is invalid.');
        }

        if (! $state->isAuthenticated()) {
            throw new AuthenticationException('The Clerk credential is invalid.');
        }

        $payload = $state->getPayload();
        $clerkUserId = is_string($payload->sub ?? null) ? $payload->sub : null;

        if ($clerkUserId === null || $clerkUserId === '') {
            throw new AuthenticationException('The Clerk credential has no subject.');
        }

        $issuer = is_string($payload->iss ?? null) ? $payload->iss : null;
        $sessionId = is_string($payload->sid ?? null) ? $payload->sid : null;

        if ($sessionId === null || $sessionId === '') {
            throw new AuthenticationException('The Clerk credential is not a user session.');
        }

        $configuredIssuer = config('clerk.issuer');

        if ($configuredIssuer !== null && $configuredIssuer !== '' && $issuer !== $configuredIssuer) {
            throw new AuthenticationException('The Clerk credential issuer is invalid.');
        }

        return new AuthenticatedClerkIdentity(
            $clerkUserId,
            $sessionId,
            $issuer,
        );
    }

    private function nullableList(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        if (! array_is_list($value) || ! $this->containsOnlyStrings($value)) {
            throw new RuntimeException('Clerk list configuration must contain strings.');
        }

        return $value;
    }

    /** @param array<mixed> $value */
    private function containsOnlyStrings(array $value): bool
    {
        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
