<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_health_endpoint_has_request_correlation(): void
    {
        $response = $this->getJson('/health');
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_health_rejects_inappropriate_methods(): void
    {
        $this->postJson('/health')->assertStatus(405);
    }
}
