<?php

namespace Tests\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

trait SubmitsApiRequests
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    protected function submit(array $payload, array $headers = []): TestResponse
    {
        RateLimiter::clear('ip:127.0.0.1');
        RateLimiter::clear(md5('anonymous-submit'.'ip:127.0.0.1'));

        return $this->withHeaders($headers)->postJson(static::URL, $payload);
    }
}
