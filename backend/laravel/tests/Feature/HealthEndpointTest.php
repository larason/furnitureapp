<?php

namespace Tests\Feature;

use Tests\TestCase;

$healthEndpoint='/health';
class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson($healthEndpoint);

        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_health_endpoint_has_request_correlation(): void
    {
        $response = $this->getJson($healthEndpoint);
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_health_rejects_inappropriate_methods(): void
    {
        $this->postJson($healthEndpoint)->assertStatus(405);
    }
}
