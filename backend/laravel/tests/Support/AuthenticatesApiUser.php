<?php

namespace Tests\Support;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Models\User;

trait AuthenticatesApiUser
{
    /** @return array<string, string> */
    protected function authenticateAs(User $user): array
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            (string) $user->clerk_user_id,
            'sess_test',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return ['Authorization' => 'Bearer session-token'];
    }
}
