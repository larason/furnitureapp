<?php

namespace Tests\Feature;

use App\Authentication\BootstrapInitialAdmin;
use App\Authentication\Clerk\ClerkUserGateway;
use App\Authentication\Clerk\ClerkUserSnapshot;
use App\Exceptions\InitialAdminBootstrapException;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InitialAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_a_verified_clerk_identity_as_the_initial_admin(): void
    {
        $this->seed(RbacSeeder::class);
        $this->useClerkGateway();

        $admin = app(BootstrapInitialAdmin::class)->bootstrap('user_initial_admin');

        $this->assertSame('user_initial_admin', $admin->clerk_user_id);
        $this->assertSame('ACTIVE', $admin->account_state);
        $this->assertTrue($admin->hasRole(RoleName::ADMIN->value));
        $this->assertCount(1, $admin->getRoleNames());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('staff_profiles', 1);
    }

    public function test_it_replaces_an_existing_customers_role_without_creating_a_second_user(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = User::factory()->customer()->create([
            'clerk_user_id' => 'user_existing_customer',
            'account_state' => null,
        ]);
        $this->useClerkGateway();

        $admin = app(BootstrapInitialAdmin::class)->bootstrap('user_existing_customer');

        $this->assertSame($customer->id, $admin->id);
        $this->assertTrue($admin->hasRole(RoleName::ADMIN->value));
        $this->assertFalse($admin->hasRole(RoleName::CUSTOMER->value));
        $this->assertCount(1, $admin->getRoleNames());
        $this->assertSame('ACTIVE', $admin->account_state);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_is_a_safe_no_op_when_retried_for_the_same_admin_identity(): void
    {
        $this->seed(RbacSeeder::class);
        $this->useClerkGateway();

        $first = app(BootstrapInitialAdmin::class)->bootstrap('user_retry_admin');
        $second = app(BootstrapInitialAdmin::class)->bootstrap('user_retry_admin');

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->hasRole(RoleName::ADMIN->value));
        $this->assertCount(1, $second->getRoleNames());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_rejects_a_second_clerk_identity_after_initial_bootstrap(): void
    {
        $this->seed(RbacSeeder::class);
        $this->useClerkGateway();
        app(BootstrapInitialAdmin::class)->bootstrap('user_first_admin');

        try {
            app(BootstrapInitialAdmin::class)->bootstrap('user_second_admin');
            $this->fail('A second initial Admin identity must be rejected.');
        } catch (InitialAdminBootstrapException $exception) {
            $this->assertSame('Initial Admin bootstrap is already configured for a different Clerk identity.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_rejects_an_unverified_clerk_identity_without_creating_an_admin(): void
    {
        $this->seed(RbacSeeder::class);
        $this->useClerkGateway(verified: false);

        try {
            app(BootstrapInitialAdmin::class)->bootstrap('user_unverified_admin');
            $this->fail('An unverified Clerk identity must be rejected.');
        } catch (InitialAdminBootstrapException $exception) {
            $this->assertSame('The Clerk identity is not eligible for initial Admin bootstrap.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_an_unavailable_clerk_identity_without_creating_an_admin(): void
    {
        $this->seed(RbacSeeder::class);
        $this->app->instance(ClerkUserGateway::class, new InitialAdminBootstrapUnavailableClerkUserGateway);

        try {
            app(BootstrapInitialAdmin::class)->bootstrap('user_missing_admin');
            $this->fail('An unavailable Clerk identity must be rejected.');
        } catch (InitialAdminBootstrapException $exception) {
            $this->assertSame('The Clerk identity could not be verified.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_command_requires_explicit_non_interactive_confirmation(): void
    {
        $this->seed(RbacSeeder::class);
        $this->useClerkGateway();

        $this->artisan('admin:bootstrap user_command_admin --confirm')
            ->expectsOutput('Target Clerk identity: user_command_admin')
            ->expectsOutput('Initial Admin bootstrap completed.')
            ->assertExitCode(0);

        $admin = User::query()->sole();
        $this->assertTrue($admin->hasRole(RoleName::ADMIN->value));
    }

    public function test_the_command_cancels_without_confirmation(): void
    {
        $this->seed(RbacSeeder::class);
        $this->useClerkGateway();

        $this->artisan('admin:bootstrap user_cancelled_admin')
            ->expectsOutput('Target Clerk identity: user_cancelled_admin')
            ->expectsConfirmation('Bootstrap this identity as the initial Admin?', 'no')
            ->expectsOutput('Initial Admin bootstrap cancelled.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_command_displays_a_dedicated_bootstrap_failure_message(): void
    {
        $this->seed(RbacSeeder::class);
        $this->useClerkGateway(verified: false);

        $this->artisan('admin:bootstrap user_unverified_command_admin --confirm')
            ->expectsOutput('Target Clerk identity: user_unverified_command_admin')
            ->expectsOutput('The Clerk identity is not eligible for initial Admin bootstrap.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    private function useClerkGateway(bool $verified = true): void
    {
        $this->app->instance(ClerkUserGateway::class, new InitialAdminBootstrapClerkUserGateway($verified));
    }
}

final class InitialAdminBootstrapClerkUserGateway implements ClerkUserGateway
{
    public function __construct(private readonly bool $verified) {}

    public function getById(string $clerkUserId): ClerkUserSnapshot
    {
        return new ClerkUserSnapshot(
            $clerkUserId,
            $clerkUserId.'@example.com',
            'Initial Admin',
            null,
            $this->verified,
        );
    }
}

final class InitialAdminBootstrapUnavailableClerkUserGateway implements ClerkUserGateway
{
    public function getById(string $clerkUserId): ClerkUserSnapshot
    {
        throw new InitialAdminBootstrapClerkUserUnavailableException('Clerk user retrieval failed.');
    }
}

final class InitialAdminBootstrapClerkUserUnavailableException extends RuntimeException {}
