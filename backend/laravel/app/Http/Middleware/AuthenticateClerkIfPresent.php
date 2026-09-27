<?php

namespace App\Http\Middleware;

use App\Authentication\ClerkTokenVerifier;
use App\Authentication\LocalUserProvisioner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateClerkIfPresent
{
    public function __construct(
        private readonly ClerkTokenVerifier $verifier,
        private readonly LocalUserProvisioner $provisioner,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            $user = $this->provisioner->resolve($this->verifier->verify($request));
            EnsureActiveAccount::assert($user);
            auth()->setUser($user);
        }

        return $next($request);
    }
}
