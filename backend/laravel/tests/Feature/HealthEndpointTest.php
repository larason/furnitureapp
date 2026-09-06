<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_up_endpoint_is_not_cacheable(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk()->assertExactJson(['status' => 'up']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
