<?php

namespace App\Authentication;

use Illuminate\Http\Request;

interface ClerkTokenVerifier
{
    public function verify(Request $request): AuthenticatedClerkIdentity;
}
