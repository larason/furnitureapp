<?php

namespace Tests\Unit;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkUserGateway;
use App\Authentication\Clerk\ClerkUserSnapshot;
use App\Authentication\LocalUserProvisioner;
use App\Exceptions\Api\ApiException;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalUserProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_one_customer_and_is_idempotent(): void
    {
        $this->seed(RbacSeeder::class);
        $provisioner = new LocalUserProvisioner(new FakeClerkUserGateway);
        $identity = new AuthenticatedClerkIdentity('user_test_123', 'sess_test_123', 'https://clerk.test');

        $first = $provisioner->resolve($identity);
        $second = $provisioner->resolve($identity);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customer_profiles', 1);
        $this->assertTrue($first->hasRole(RoleName::CUSTOMER->value));
        $this->assertNull($first->getRawOriginal('password'));
        $this->assertNotNull($first->email_verified_at);
    }

    public function test_it_does_not_link_an_unmapped_user_by_email(): void
    {
        $this->seed(RbacSeeder::class);
        User::factory()->create(['email' => 'same@example.com']);
        $provisioner = new LocalUserProvisioner(new FakeClerkUserGateway);
        $identity = new AuthenticatedClerkIdentity('user_test_new', null, null);

        $this->expectException(ApiException::class);
        $provisioner->resolve($identity);
    }

    public function test_it_does_not_provision_an_unverified_email(): void
    {
        $this->seed(RbacSeeder::class);
        $provisioner = new LocalUserProvisioner(new FakeClerkUserGateway(verified: false));
        $identity = new AuthenticatedClerkIdentity('user_unverified_1', 'sess_1', 'https://clerk.test');

        try {
            $provisioner->resolve($identity);
            $this->fail('Expected unverified email to be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame('INVALID_AUTHENTICATION', $exception->errorCode()->value);
            $this->assertSame(401, $exception->status());
        }

        $this->assertDatabaseMissing('users', ['clerk_user_id' => 'user_unverified_1']);
    }

    public function test_it_provisions_a_verified_email_without_phone(): void
    {
        $this->seed(RbacSeeder::class);
        $provisioner = new LocalUserProvisioner(new FakeClerkUserGateway(phone: null));
        $identity = new AuthenticatedClerkIdentity('user_nophone_1', 'sess_2', 'https://clerk.test');

        $user = $provisioner->resolve($identity);

        $this->assertSame('user_nophone_1', $user->clerk_user_id);
        $this->assertTrue($user->hasRole(RoleName::CUSTOMER->value));
        $this->assertNull($user->phone);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->getRawOriginal('password'));
    }

    public function test_it_provisions_a_verified_email_without_name(): void
    {
        $this->seed(RbacSeeder::class);
        $provisioner = new LocalUserProvisioner(new FakeClerkUserGateway(name: null));
        $identity = new AuthenticatedClerkIdentity('user_noname_1', 'sess_3', 'https://clerk.test');

        $user = $provisioner->resolve($identity);

        $this->assertNull($user->name);
        $this->assertTrue($user->hasRole(RoleName::CUSTOMER->value));
    }
}

final class FakeClerkUserGateway implements ClerkUserGateway
{
    public function __construct(
        private readonly bool $verified = true,
        private readonly ?string $phone = null,
        private readonly ?string $name = 'Test Customer',
    ) {}

    public function getById(string $clerkUserId): ClerkUserSnapshot
    {
        return new ClerkUserSnapshot($clerkUserId, 'same@example.com', $this->name, $this->phone, $this->verified);
    }
}
