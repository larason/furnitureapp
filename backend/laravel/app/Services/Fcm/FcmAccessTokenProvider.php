<?php

namespace App\Services\Fcm;

interface FcmAccessTokenProvider
{
    /** @return array{access_token: string} */
    public function token(): array;
}
