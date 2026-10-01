<?php

namespace Tests\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

trait SubmitsApiRequests
{
    abstract protected function endpoint(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    protected function submit(array $payload, array $headers = []): TestResponse
    {
        RateLimiter::for('anonymous-submit', static fn (): Limit => Limit::none());

        return $this->withHeaders($headers)->postJson($this->endpoint(), $payload);
    }
}
