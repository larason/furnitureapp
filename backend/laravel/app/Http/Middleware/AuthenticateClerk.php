<?php

namespace App\Http\Middleware;

use App\Authentication\ClerkTokenVerifier;
use App\Authentication\LocalUserProvisioner;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateClerk
{
    public function __construct(
        private readonly ClerkTokenVerifier $verifier,
        private readonly LocalUserProvisioner $provisioner,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->verifier->verify($request);
        $user = $this->provisioner->resolve($identity);

        EnsureActiveAccount::assert($user);

        auth()->setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }
}
