<?php

namespace App\Authentication;

use App\Authentication\Clerk\ClerkUserGateway;
use App\Authentication\Clerk\ClerkUserSnapshot;
use App\Exceptions\InitialAdminBootstrapException;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Throwable;

final class BootstrapInitialAdmin
{
    private const ACTIVE_ACCOUNT_STATE = 'ACTIVE';

    public function __construct(
        private readonly ClerkUserGateway $gateway,
        private readonly LocalUserProvisioner $provisioner,
    ) {}

    public function bootstrap(string $clerkUserId): User
    {
        $snapshot = $this->trustedSnapshot($clerkUserId);

        return DB::transaction(fn (): User => $this->bootstrapWithinTransaction($snapshot), 3);
    }

    private function trustedSnapshot(string $clerkUserId): ClerkUserSnapshot
    {
        $clerkUserId = trim($clerkUserId);

        if ($clerkUserId === '') {
            throw new InitialAdminBootstrapException('A Clerk user ID is required.');
        }

        try {
            $snapshot = $this->gateway->getById($clerkUserId);
        } catch (Throwable $exception) {
            throw new InitialAdminBootstrapException('The Clerk identity could not be verified.', 0, $exception);
        }

        if ($snapshot->clerkUserId !== $clerkUserId || ! $snapshot->emailVerified) {
            throw new InitialAdminBootstrapException('The Clerk identity is not eligible for initial Admin bootstrap.');
        }

        return $snapshot;
    }

    private function bootstrapWithinTransaction(ClerkUserSnapshot $snapshot): User
    {
        $adminRole = $this->lockAdminRole();
        $existingAdmin = $this->lockExistingAdmin();

        if ($existingAdmin !== null) {
            return $this->alreadyConfiguredAdmin($existingAdmin, $snapshot);
        }

        $user = User::query()->where('clerk_user_id', $snapshot->clerkUserId)->lockForUpdate()->first();

        if ($user === null) {
            $user = $this->provisioner->resolve(new AuthenticatedClerkIdentity($snapshot->clerkUserId, null, null));
        }

        $user->syncRoles([$adminRole]);
        $user->forceFill(['account_state' => self::ACTIVE_ACCOUNT_STATE])->save();
        StaffProfile::firstOrCreate(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function lockAdminRole(): Role
    {
        $role = Role::query()
            ->where('name', RoleName::ADMIN->value)
            ->where('guard_name', config('auth.defaults.guard'))
            ->lockForUpdate()
            ->first();

        if ($role === null) {
            throw new InitialAdminBootstrapException('The ADMIN role has not been provisioned.');
        }

        return $role;
    }

    private function lockExistingAdmin(): ?User
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RoleName::ADMIN->value))
            ->lockForUpdate()
            ->first();
    }

    private function alreadyConfiguredAdmin(User $admin, ClerkUserSnapshot $snapshot): User
    {
        if ($admin->clerk_user_id !== $snapshot->clerkUserId) {
            throw new InitialAdminBootstrapException('Initial Admin bootstrap is already configured for a different Clerk identity.');
        }

        if ($admin->getRoleNames()->count() !== 1) {
            throw new InitialAdminBootstrapException('The existing Admin role assignment is not eligible for bootstrap retry.');
        }

        return $admin;
    }
}
