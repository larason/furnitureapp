<?php

namespace App\Authentication\Clerk;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequest;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequestOptions;
use Clerk\Backend\Helpers\Jwks\ErrorReason;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

final class OfficialClerkTokenVerifier implements ClerkTokenVerifier
{
    /** @var Closure(Request, AuthenticateRequestOptions): mixed */
    private readonly Closure $authenticateRequest;

    public function __construct(?Closure $authenticateRequest = null)
    {
        $this->authenticateRequest = $authenticateRequest ?? static fn (
            Request $request,
            AuthenticateRequestOptions $options,
        ): mixed => AuthenticateRequest::authenticateRequest($request, $options);
    }

    public function verify(Request $request): AuthenticatedClerkIdentity
    {
        if ($request->bearerToken() === null) {
            throw ClerkAuthenticationFailure::missing();
        }

        $audiences = $this->nullableList(config('clerk.audiences'));
        $authorizedParties = $this->nullableList(config('clerk.authorized_parties'));

        try {
            $state = ($this->authenticateRequest)($request, new AuthenticateRequestOptions(
                secretKey: config('clerk.secret_key'),
                jwtKey: config('clerk.jwt_key'),
                audiences: $audiences,
                authorizedParties: $authorizedParties,
                acceptsToken: ['session_token'],
            ));
        } catch (\Throwable) {
            throw ClerkAuthenticationFailure::external();
        }

        if (! $state->isAuthenticated()) {
            throw $this->failureFor($state->getErrorReason());
        }

        $payload = $state->getPayload();
        $clerkUserId = is_string($payload->sub ?? null) ? $payload->sub : null;

        if ($clerkUserId === null || $clerkUserId === '') {
            throw ClerkAuthenticationFailure::invalid();
        }

        $issuer = is_string($payload->iss ?? null) ? $payload->iss : null;
        $sessionId = is_string($payload->sid ?? null) ? $payload->sid : null;

        if ($sessionId === null || $sessionId === '') {
            throw ClerkAuthenticationFailure::invalid();
        }

        $configuredIssuer = config('clerk.issuer');

        if ($configuredIssuer !== null && $configuredIssuer !== '' && $issuer !== $configuredIssuer) {
            throw ClerkAuthenticationFailure::invalid();
        }

        if (! $this->claimMatches($payload->aud ?? null, $audiences)
            || ! $this->claimMatches($payload->azp ?? null, $authorizedParties)) {
            throw ClerkAuthenticationFailure::invalid();
        }

        $hasSessionStatus = property_exists($payload, 'sts');
        $sessionStatus = $payload->sts ?? null;

        if ($hasSessionStatus && ! is_string($sessionStatus)) {
            throw ClerkAuthenticationFailure::invalid();
        }

        if ($sessionStatus === 'pending') {
            throw ClerkAuthenticationFailure::pending();
        }

        if ($hasSessionStatus && $sessionStatus !== 'active') {
            throw ClerkAuthenticationFailure::invalid();
        }

        return new AuthenticatedClerkIdentity(
            $clerkUserId,
            $sessionId,
            $issuer,
        );
    }

    private function failureFor(?ErrorReason $reason): ClerkAuthenticationFailure
    {
        return match ($reason?->getId()) {
            'session-token-missing' => ClerkAuthenticationFailure::missing(),
            'token-expired' => ClerkAuthenticationFailure::expired(),
            'jwk-failed-to-load',
            'jwk-remote-invalid',
            'jwk-failed-to-resolve' => ClerkAuthenticationFailure::external(),
            'jwk-local-invalid',
            'secret-key-missing' => ClerkAuthenticationFailure::internal(),
            null => ClerkAuthenticationFailure::missing(),
            default => ClerkAuthenticationFailure::invalid(),
        };
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

    /** @param array<int, string>|null $allowed */
    private function claimMatches(mixed $claim, ?array $allowed): bool
    {
        if ($allowed === null) {
            return true;
        }

        $values = is_string($claim) ? [$claim] : $claim;

        return is_array($values)
            && $values !== []
            && $this->containsOnlyStrings($values)
            && array_intersect($values, $allowed) !== [];
    }
}
