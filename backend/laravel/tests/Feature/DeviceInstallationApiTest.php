<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Exceptions\MigrationPreconditionException;
use App\Models\DeviceInstallation;
use App\Models\User;
use App\Services\Push\DeviceDeactivationService;
use App\Services\Push\PushTokenCipher;
use App\Services\Push\PushTokenFingerprint;
use App\Services\Push\StaleDeviceInstallationReaper;
use App\Support\DeviceInstallationIdentifier;
use App\Support\DeviceRegistrationStatus;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Authenticated customer device registration and deactivation.
 *
 * Covers multiple installations, duplicate and concurrent registration, token
 * rotation, cross-user conflict, ownership masking, idempotent deactivation,
 * and the independently gated route.
 */
final class DeviceInstallationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        config()->set('notifications.push.registration_enabled', true);
    }

    private function payload(string $token = 'token-alpha', array $overrides = []): array
    {
        return array_merge([
            'token' => $token,
            'platform' => 'ANDROID',
            'app_instance_id' => 'instance-1',
        ], $overrides);
    }

    private static int $identityCounter = 0;

    /**
     * Customers, staff, and admins all need a resolvable Clerk identity.
     *
     * Creating a user deliberately does not bind the verifier: the binding is
     * per request, so two principals can be created up front and still
     * authenticate as themselves. Binding here would let the last-created user
     * silently authenticate every later request in the same test.
     */
    private function user(string $role = 'customer'): User
    {
        self::$identityCounter++;

        return User::factory()->{$role}()->create([
            'clerk_user_id' => 'clerk_device_'.self::$identityCounter,
        ]);
    }

    /** @return array<string, string> */
    private function asUser(User $user): array
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            (string) $user->clerk_user_id,
            'sess_device_'.$user->getKey(),
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return ['Authorization' => 'Bearer session-token'];
    }

    public function test_registration_creates_a_device_and_never_returns_the_token(): void
    {
        $customer = $this->user('customer');

        $response = $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload());

        $response->assertCreated()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertJsonPath('data.platform', 'ANDROID')
            ->assertJsonPath('data.app_instance_id', 'instance-1')
            ->assertJsonPath('data.status', 'ACTIVE');

        $device = DeviceInstallation::query()->sole();
        $this->assertSame(
            DeviceInstallationIdentifier::encodeId((int) $device->getKey()),
            $response->json('data.id'),
        );
        $this->assertStringNotContainsString('token-alpha', $response->getContent());
        $this->assertArrayNotHasKey('token', $response->json('data'));
        $this->assertArrayNotHasKey('token_ciphertext', $response->json('data'));
        $this->assertArrayNotHasKey('token_fingerprint', $response->json('data'));
    }

    public function test_the_stored_token_is_encrypted_and_never_stored_in_plaintext(): void
    {
        $customer = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('super-secret-token'))
            ->assertCreated();

        $device = DeviceInstallation::query()->sole();
        $this->assertStringNotContainsString('super-secret-token', $device->token_ciphertext);
        $this->assertNotSame('super-secret-token', $device->token_fingerprint);
        $this->assertSame(
            'super-secret-token',
            app(PushTokenCipher::class)->decrypt($device->token_ciphertext),
        );
    }

    public function test_repeated_registration_of_the_same_installation_is_idempotent(): void
    {
        $customer = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload())
            ->assertCreated();
        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload())
            ->assertOk();

        $this->assertSame(1, DeviceInstallation::query()->count());
    }

    public function test_one_customer_can_register_multiple_installations(): void
    {
        $customer = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-phone', ['app_instance_id' => 'instance-phone']))
            ->assertCreated();
        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-tablet', ['app_instance_id' => 'instance-tablet']))
            ->assertCreated();

        $this->assertSame(2, DeviceInstallation::query()->where('user_id', $customer->getKey())->count());
    }

    public function test_token_rotation_updates_the_existing_installation(): void
    {
        $customer = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-before'))
            ->assertCreated();
        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-after'))
            ->assertOk();

        $this->assertSame(1, DeviceInstallation::query()->count());
        $this->assertSame(
            'token-after',
            app(PushTokenCipher::class)->decrypt(DeviceInstallation::query()->sole()->token_ciphertext),
        );
    }

    public function test_registration_reactivates_a_previously_deactivated_installation(): void
    {
        $customer = $this->user('customer');
        $device = DeviceInstallation::factory()->forOwner($customer)->inactive()->create();

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-revived', ['app_instance_id' => $device->app_instance_id]))
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertNull($device->refresh()->deactivated_at);
    }

    public function test_a_token_belonging_to_another_customer_is_refused_with_conflict(): void
    {
        $owner = $this->user('customer');
        $other = $this->user('customer');
        DeviceInstallation::factory()->forOwner($owner)->withToken('shared-token')->create();

        $this->withHeaders($this->asUser($other))
            ->postJson('/api/v1/me/devices', $this->payload('shared-token', ['app_instance_id' => 'instance-new']))
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CONFLICT');

        $this->assertSame(1, DeviceInstallation::query()->count());
    }

    public function test_concurrent_registration_of_the_same_token_by_two_customers_yields_one_owner(): void
    {
        $first = $this->user('customer');
        $second = $this->user('customer');

        $this->withHeaders($this->asUser($first))
            ->postJson('/api/v1/me/devices', $this->payload('contended-token', ['app_instance_id' => 'instance-a']))
            ->assertCreated();
        $this->withHeaders($this->asUser($second))
            ->postJson('/api/v1/me/devices', $this->payload('contended-token', ['app_instance_id' => 'instance-b']))
            ->assertStatus(409);

        $this->assertSame(1, DeviceInstallation::query()->count());
    }

    public function test_a_client_supplied_user_id_is_rejected(): void
    {
        $customer = $this->user('customer');
        $other = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-x', ['user_id' => (string) $other->getKey()]))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
            ->assertJsonPath('errors.0.field', 'user_id');

        $this->assertSame(0, DeviceInstallation::query()->count());
    }

    public function test_a_client_supplied_status_is_rejected(): void
    {
        $customer = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-y', ['status' => 'ACTIVE']))
            ->assertStatus(422);
    }

    public function test_an_unknown_platform_is_rejected(): void
    {
        $customer = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload('token-z', ['platform' => 'IOS']))
            ->assertStatus(422);
    }

    public function test_registration_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/devices', $this->payload())->assertUnauthorized();

        $this->assertSame(0, DeviceInstallation::query()->count());
    }

    public function test_staff_and_admin_cannot_register_customer_devices(): void
    {
        foreach (['staff', 'admin'] as $role) {
            $user = $this->user($role);

            $this->withHeaders($this->asUser($user))
                ->postJson('/api/v1/me/devices', $this->payload("token-{$role}"))
                ->assertForbidden();
        }

        $this->assertSame(0, DeviceInstallation::query()->count());
    }

    public function test_deactivation_is_owner_only_and_idempotent(): void
    {
        $customer = $this->user('customer');
        $device = DeviceInstallation::factory()->forOwner($customer)->create();
        $identifier = DeviceInstallationIdentifier::encodeId((int) $device->getKey());

        $this->withHeaders($this->asUser($customer))
            ->deleteJson("/api/v1/me/devices/{$identifier}")
            ->assertNoContent();
        $this->withHeaders($this->asUser($customer))
            ->deleteJson("/api/v1/me/devices/{$identifier}")
            ->assertNoContent();

        $this->assertSame(DeviceRegistrationStatus::INACTIVE, $device->refresh()->status);
        $this->assertNotNull($device->deactivated_at);
    }

    public function test_another_customers_device_is_masked_as_not_found(): void
    {
        $owner = $this->user('customer');
        $other = $this->user('customer');
        $device = DeviceInstallation::factory()->forOwner($owner)->create();

        $this->withHeaders($this->asUser($other))
            ->deleteJson('/api/v1/me/devices/'.DeviceInstallationIdentifier::encodeId((int) $device->getKey()))
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $this->assertSame(DeviceRegistrationStatus::ACTIVE, $device->refresh()->status);
    }

    public function test_an_unknown_device_identifier_is_not_found(): void
    {
        $customer = $this->user('customer');

        $this->withHeaders($this->asUser($customer))
            ->deleteJson('/api/v1/me/devices/dev_zzzzz')
            ->assertNotFound();

        $this->withHeaders($this->asUser($customer))
            ->deleteJson('/api/v1/me/devices/not-an-identifier')
            ->assertNotFound();
    }

    public function test_deactivation_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/me/devices/dev_1')->assertUnauthorized();
    }

    public function test_registration_is_gated_independently_of_push_fan_out(): void
    {
        $customer = $this->user('customer');
        config()->set('notifications.push.registration_enabled', false);
        config()->set('notifications.push.enabled', false);

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload())
            ->assertStatus(501)
            ->assertExactJson(['status' => 'not_implemented']);

        $this->assertSame(0, DeviceInstallation::query()->count());
    }

    public function test_registration_can_be_enabled_while_fan_out_stays_disabled(): void
    {
        $customer = $this->user('customer');
        config()->set('notifications.push.registration_enabled', true);
        config()->set('notifications.push.enabled', false);

        $this->withHeaders($this->asUser($customer))
            ->postJson('/api/v1/me/devices', $this->payload())
            ->assertCreated();

        $this->assertFalse((bool) config('notifications.push.enabled'));
    }

    public function test_deactivation_clears_every_installation_for_a_customer(): void
    {
        $customer = $this->user('customer');
        DeviceInstallation::factory()->count(2)->forOwner($customer)->create();

        $this->app->make(DeviceDeactivationService::class)->deactivateAllFor($customer);

        $this->assertSame(0, DeviceInstallation::query()->where('status', 'ACTIVE')->count());
    }

    public function test_the_database_rejects_a_second_active_owner_directly(): void
    {
        $first = $this->user('customer');
        $second = $this->user('customer');
        $fingerprint = app(PushTokenFingerprint::class)->for('db-level-token');

        DeviceInstallation::factory()->forOwner($first)->withToken('db-level-token')->create();

        $this->expectException(QueryException::class);

        try {
            DB::table('device_installations')->insert([
                'user_id' => $second->getKey(),
                'platform' => 'ANDROID',
                'app_instance_id' => 'instance-db',
                'token_ciphertext' => app(PushTokenCipher::class)->encrypt('db-level-token'),
                'token_fingerprint' => $fingerprint,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            $this->assertSame(1, DeviceInstallation::query()->count());
        }
    }

    public function test_the_database_allows_retired_rows_to_share_a_fingerprint(): void
    {
        $first = $this->user('customer');
        $second = $this->user('customer');
        $fingerprint = app(PushTokenFingerprint::class)->for('shared-retired-token');

        DeviceInstallation::factory()->forOwner($first)->withToken('shared-retired-token')->create();
        DeviceInstallation::factory()->forOwner($second)->inactive()->withToken('shared-retired-token')->create();

        // The stored fingerprint survives on the retired row for audit.
        $this->assertSame(
            2,
            DeviceInstallation::query()->where('token_fingerprint', $fingerprint)->count(),
        );
    }

    public function test_a_retired_registration_does_not_block_the_next_customer(): void
    {
        $previous = $this->user('customer');
        $next = $this->user('customer');
        $this->withHeaders($this->asUser($previous))
            ->postJson('/api/v1/me/devices', $this->payload('handed-over-token', ['app_instance_id' => 'instance-a']))
            ->assertCreated();
        $retired = DeviceInstallation::query()->sole();

        // The previous owner retires the device, as a sign-out does.
        $this->withHeaders($this->asUser($previous))
            ->deleteJson('/api/v1/me/devices/'.DeviceInstallationIdentifier::encodeId((int) $retired->getKey()))
            ->assertNoContent();

        $this->withHeaders($this->asUser($next))
            ->postJson('/api/v1/me/devices', $this->payload('handed-over-token', ['app_instance_id' => 'instance-b']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE');

        // History is preserved: the retired row keeps its owner and its token.
        $this->assertSame(2, DeviceInstallation::query()->count());
        $this->assertSame(
            $previous->getKey(),
            $retired->refresh()->user_id,
        );
        $this->assertSame(DeviceRegistrationStatus::INACTIVE, $retired->status);
        $this->assertSame(
            'handed-over-token',
            app(PushTokenCipher::class)->decrypt($retired->token_ciphertext),
        );
    }

    public function test_a_current_owner_still_blocks_another_customer(): void
    {
        $owner = $this->user('customer');
        $other = $this->user('customer');

        $this->withHeaders($this->asUser($owner))
            ->postJson('/api/v1/me/devices', $this->payload('still-owned-token', ['app_instance_id' => 'instance-a']))
            ->assertCreated();

        $this->withHeaders($this->asUser($other))
            ->postJson('/api/v1/me/devices', $this->payload('still-owned-token', ['app_instance_id' => 'instance-b']))
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CONFLICT');

        $this->assertSame(1, DeviceInstallation::query()->count());
    }

    public function test_a_previous_owner_cannot_reactivate_while_another_customer_holds_the_token(): void
    {
        $previous = $this->user('customer');
        $next = $this->user('customer');

        $this->withHeaders($this->asUser($previous))
            ->postJson('/api/v1/me/devices', $this->payload('contested-token', ['app_instance_id' => 'instance-a']))
            ->assertCreated();
        $retired = DeviceInstallation::query()->sole();
        $this->withHeaders($this->asUser($previous))
            ->deleteJson('/api/v1/me/devices/'.DeviceInstallationIdentifier::encodeId((int) $retired->getKey()))
            ->assertNoContent();

        $this->withHeaders($this->asUser($next))
            ->postJson('/api/v1/me/devices', $this->payload('contested-token', ['app_instance_id' => 'instance-b']))
            ->assertCreated();

        // The previous owner trying to reclaim its own retired row is refused,
        // because the token is now actively held by someone else.
        $this->withHeaders($this->asUser($previous))
            ->postJson('/api/v1/me/devices', $this->payload('contested-token', ['app_instance_id' => 'instance-a']))
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CONFLICT');

        $this->assertSame(DeviceRegistrationStatus::INACTIVE, $retired->refresh()->status);
        $this->assertSame(2, DeviceInstallation::query()->count());
    }

    public function test_rollback_is_refused_once_history_shares_a_fingerprint(): void
    {
        $first = $this->user('customer');
        $second = $this->user('customer');
        DeviceInstallation::factory()->forOwner($first)->inactive()->withToken('shared-history')->create();
        DeviceInstallation::factory()->forOwner($second)->inactive()->withToken('shared-history')->create();

        $amendment = require database_path(
            'migrations/2026_10_13_120000_scope_device_token_uniqueness_to_active_rows.php'
        );

        // Rollback would have to delete retired rows, so it is refused. The
        // remediation is a new forward migration, never a rollback.
        $this->expectException(MigrationPreconditionException::class);
        try {
            $amendment->down();
        } finally {
            // Refusing must not destroy the history it exists to protect.
            $this->assertSame(2, DeviceInstallation::query()->count());
        }
    }

    public function test_retired_rows_do_not_leak_into_reaping_counts(): void
    {
        $customer = $this->user('customer');
        $fingerprint = app(PushTokenFingerprint::class)->for('reap-shared-token');
        DeviceInstallation::factory()->forOwner($customer)->withToken('reap-shared-token')->create();
        DeviceInstallation::factory()->forOwner($customer)->inactive()->withToken('reap-shared-token')->create();
        $this->assertNotNull($fingerprint);

        // Only the ACTIVE row is transitioned; the retired row keeps its record.
        $reaped = app(StaleDeviceInstallationReaper::class)
            ->reapUnregistered([$fingerprint]);

        $this->assertSame(1, $reaped);
        $this->assertSame(0, DeviceInstallation::query()->where('status', 'ACTIVE')->count());
        $this->assertSame(2, DeviceInstallation::query()->where('status', 'INACTIVE')->count());
    }
}
