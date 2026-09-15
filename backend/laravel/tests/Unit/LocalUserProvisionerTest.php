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
}

final class FakeClerkUserGateway implements ClerkUserGateway
{
    public function getById(string $clerkUserId): ClerkUserSnapshot
    {
        return new ClerkUserSnapshot($clerkUserId, 'same@example.com', 'Test Customer', null, true);
    }
}
