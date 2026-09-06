<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2.6 routing-foundation smoke tests.
 *
 * These verify the transport/middleware topology only, not domain behavior:
 * stub controllers return 501; protected routes reject unauthenticated
 * callers with 401 via the `auth` middleware boundary; and the
 * Operational/Administrative guards deny-by-default, so an authenticated
 * (but not-yet-roleable) principal receives 403 on every protected boundary.
 */
class ApiRoutingSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrative_routes_deny_authenticated_non_admin_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/staff')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/admin/staff')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/users')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/admin/products')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/products')->assertForbidden();
        $this->actingAs($user)->patchJson('/api/v1/categories/demo-category')->assertForbidden();
    }

    public function test_operational_routes_deny_authenticated_non_staff_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/orders')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/orders/OD-1/accept')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/orders/OD-1/ship')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/inventory')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/inventory/prod-1/adjust')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/requests')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/enquiries')->assertForbidden();
    }

    public function test_public_catalog_routes_do_not_require_authentication(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(501);
        $this->getJson('/api/v1/products/demo-sofa')->assertStatus(501);
        $this->getJson('/api/v1/products/demo-sofa/variants')->assertStatus(501);
        $this->getJson('/api/v1/products/demo-sofa/variants/var-1')->assertStatus(501);
        $this->getJson('/api/v1/categories')->assertStatus(501);
        $this->getJson('/api/v1/categories/demo-category')->assertStatus(501);
    }

    public function test_public_anonymous_submission_routes_do_not_require_authentication(): void
    {
        $this->postJson('/api/v1/auth/register')->assertStatus(501);
        $this->postJson('/api/v1/auth/login')->assertStatus(501);
        $this->postJson('/api/v1/auth/password/forgot')->assertStatus(501);
        $this->postJson('/api/v1/auth/password/reset')->assertStatus(501);
        $this->postJson('/api/v1/requests')->assertStatus(501);
        $this->postJson('/api/v1/enquiries')->assertStatus(501);
    }

    public function test_customer_self_service_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me/orders')->assertUnauthorized();
        $this->getJson('/api/v1/me/requests')->assertUnauthorized();
        $this->getJson('/api/v1/me/enquiries')->assertUnauthorized();
        $this->getJson('/api/v1/me/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/me/cart')->assertUnauthorized();
    }

    public function test_operational_routes_do_not_become_public(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->getJson('/api/v1/orders/OD-0001')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/accept')->assertUnauthorized();
        $this->getJson('/api/v1/inventory')->assertUnauthorized();
        $this->getJson('/api/v1/inventory/inv-1')->assertUnauthorized();
        $this->postJson('/api/v1/inventory/prod-1/adjust')->assertUnauthorized();
        $this->getJson('/api/v1/requests')->assertUnauthorized();
        $this->getJson('/api/v1/enquiries')->assertUnauthorized();
    }

    public function test_administrative_routes_do_not_become_public(): void
    {
        $this->getJson('/api/v1/admin/staff')->assertUnauthorized();
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->getJson('/api/v1/admin/audit-logs')->assertUnauthorized();
        $this->postJson('/api/v1/products')->assertUnauthorized();
        $this->postJson('/api/v1/categories')->assertUnauthorized();
        $this->patchJson('/api/v1/categories/demo')->assertUnauthorized();
    }

    public function test_removed_staff_aliases_are_not_registered(): void
    {
        foreach ([
            '/api/v1/staff/orders',
            '/api/v1/staff/orders/OD-0001',
            '/api/v1/staff/inventory',
            '/api/v1/staff/products',
            '/api/v1/staff/requests',
            '/api/v1/staff/enquiries',
        ] as $path) {
            $this->getJson($path)->assertNotFound();
        }
    }

    public function test_order_state_actions_are_post_actions_not_generic_patch(): void
    {
        $this->patchJson('/api/v1/orders/OD-0001')->assertMethodNotAllowed();
        $this->postJson('/api/v1/orders/OD-0001/process')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/ready-for-pickup')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/ship')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/deliver')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/complete')->assertUnauthorized();
        $this->postJson('/api/v1/orders/OD-0001/delivery-fee')->assertUnauthorized();
    }

    public function test_versioned_routes_exist_only_under_api_v1(): void
    {
        $this->get('/v1/products')->assertNotFound();
        $this->get('/api/products')->assertNotFound();
        $this->get('/api/v2/products')->assertNotFound();
        $this->getJson('/api/v1/products')->assertStatus(501);
    }

    public function test_health_and_infrastructure_marker_are_not_domain_routes(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }
}
