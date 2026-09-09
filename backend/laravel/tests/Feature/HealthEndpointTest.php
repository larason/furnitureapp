<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    private const HEALTH_ENDPOINT = '/health';

    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson(self::HEALTH_ENDPOINT);

        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_health_endpoint_has_request_correlation(): void
    {
        $response = $this->getJson(self::HEALTH_ENDPOINT);
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_health_rejects_inappropriate_methods(): void
    {
        $this->postJson(self::HEALTH_ENDPOINT)->assertStatus(405);
    }
}
