<?php

namespace App\Services\Fcm;

final readonly class FcmSendResult
{
    public function __construct(
        public string $providerMessageId,
    ) {}
}
