<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\PermissionName;
use App\Support\UserIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_only_customers_with_private_paginated_response(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_1']);
        $olderCustomer = User::factory()->customer()->create(['created_at' => now()->subMinute()]);
        $newerCustomer = User::factory()->customer()->create(['created_at' => now()]);
        $staff = User::factory()->staff()->create();
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->asUser($admin)->getJson('/api/v1/users?per_page=1');

        $response->assertOk()
            ->assertJsonPath('data.0.id', UserIdentifier::encodeId($newerCustomer->id))
            ->assertJsonPath('data.0.role', 'CUSTOMER')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.has_next', true)
            ->assertJsonPath('meta.pagination.has_previous', false);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains(UserIdentifier::encodeId($staff->id), $returnedIds);
        $this->assertNotContains(UserIdentifier::encodeId($otherAdmin->id), $returnedIds);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Authorization', (string) $response->headers->get('Vary'));
    }

    public function test_admin_can_view_a_customer_but_non_customer_targets_are_masked(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_1']);
        $customer = User::factory()->customer()->create(['name' => null, 'phone' => null]);
        $staff = User::factory()->staff()->create();
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->asUser($admin)->getJson('/api/v1/users/'.UserIdentifier::encodeId($customer->id));

        $response->assertOk()
            ->assertJsonPath('data.id', UserIdentifier::encodeId($customer->id))
            ->assertJsonPath('data.name', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.role', 'CUSTOMER');

        foreach ([$staff, $otherAdmin] as $target) {
            $this->asUser($admin)
                ->getJson('/api/v1/users/'.UserIdentifier::encodeId($target->id))
                ->assertNotFound()
                ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        }

        $this->asUser($admin)
            ->getJson('/api/v1/users/user_unknown')
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $event = AuditEvent::query()->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('CUSTOMER_VIEWED', $event->action);
        $this->assertSame('user', $event->resource_type);
        $this->assertSame(UserIdentifier::encodeId($customer->id), $event->resource_id);
        $this->assertSame(['result' => 'SUCCESS'], $event->resulting_state);
    }

    public function test_admin_customer_list_records_only_successful_collection_access(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_audit']);
        User::factory()->count(2)->customer()->create();

        $this->asUser($admin)->getJson('/api/v1/users?per_page=1')->assertOk();

        $event = AuditEvent::query()->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('CUSTOMER_LIST_VIEWED', $event->action);
        $this->assertSame('user', $event->resource_type);
        $this->assertSame('customer_collection', $event->resource_id);
        $this->assertSame(['result' => 'SUCCESS', 'returned_count' => 1], $event->resulting_state);
    }

    public function test_customer_staff_and_unpermitted_admin_cannot_browse_customers(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'customer_1']);
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_1']);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_1']);
        $target = User::factory()->customer()->create();
        Role::findByName('ADMIN')->revokePermissionTo(PermissionName::USERS_MANAGE_AUTHORIZED->value);

        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->getJson('/api/v1/users/'.UserIdentifier::encodeId($target->id))->assertUnauthorized();

        foreach ([$customer, $staff, $admin] as $actor) {
            $this->asUser($actor)->getJson('/api/v1/users')->assertForbidden();
            $this->asUser($actor)->getJson('/api/v1/users/'.UserIdentifier::encodeId($target->id))->assertForbidden();
        }
    }

    public function test_customer_response_never_contains_sensitive_or_internal_fields(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_1']);
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'user_private']);

        $data = $this->asUser($admin)
            ->getJson('/api/v1/users/'.UserIdentifier::encodeId($customer->id))
            ->assertOk()
            ->json('data');

        $this->assertSame(['id', 'role', 'name', 'email', 'phone', 'email_verified', 'created_at', 'updated_at'], array_keys($data));

        foreach (['clerk_user_id', 'password', 'password_hash', 'remember_token', 'permissions', 'tokens', 'sessions', 'staff_state'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
    }

    public function test_collection_rejects_unknown_and_invalid_pagination_parameters(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_1']);

        $this->asUser($admin)->getJson('/api/v1/users?role=CUSTOMER')->assertUnprocessable();
        $this->asUser($admin)->getJson('/api/v1/users?page=0')->assertUnprocessable();
        $this->asUser($admin)->getJson('/api/v1/users?per_page=101')->assertUnprocessable();
    }

    public function test_collection_query_count_does_not_grow_per_customer(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'admin_1']);
        User::factory()->count(2)->customer()->create();

        $this->asUser($admin)->getJson('/api/v1/users')->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->asUser($admin)->getJson('/api/v1/users')->assertOk();
        $smallCollectionQueryCount = count(DB::getQueryLog());

        User::factory()->count(20)->customer()->create();
        DB::flushQueryLog();
        $this->asUser($admin)->getJson('/api/v1/users')->assertOk();

        $this->assertSame($smallCollectionQueryCount, count(DB::getQueryLog()));
    }

    private function asUser(User $user): self
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            $user->clerk_user_id,
            'sess_123',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return $this->withHeaders(['Authorization' => 'Bearer session-token']);
    }
}
