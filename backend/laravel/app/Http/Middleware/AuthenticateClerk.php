<?php

namespace App\Http\Middleware;

use App\Authentication\ClerkTokenVerifier;
use App\Authentication\LocalUserProvisioner;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
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

        if ($user->account_state !== null && $user->account_state !== 'ACTIVE') {
            throw new AuthorizationException('The authenticated account is not active.');
        }

        auth()->setUser($user);

        return $next($request);
    }
}
