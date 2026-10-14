<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UpdateMeRequest;
use App\Models\User;
use App\Services\UpdateCustomerProfile;
use App\Support\RoleName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends V1Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->profileResponse($user);
    }

    public function update(UpdateMeRequest $request, UpdateCustomerProfile $profile): JsonResponse
    {
        $user = $request->user();
        $role = $this->resolveRole($user);

        return $this->profileResponse($profile->update($user, $request->validated()), $role);
    }

    private function profileResponse(User $user, ?RoleName $role = null): JsonResponse
    {
        $role ??= $this->resolveRole($user);

        return response()->json(['data' => [
            'id' => (string) $user->getKey(),
            'role' => $role->value,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->email_verified_at !== null,
            'account_state' => $user->account_state,
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ]])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ]);
    }

    private function resolveRole(User $user): RoleName
    {
        $roles = $user->getRoleNames();

        if ($roles->count() !== 1) {
            throw new AuthorizationException('The authenticated account is not eligible.');
        }

        $role = $roles->first();

        $resolved = is_string($role) ? RoleName::tryFrom($role) : null;

        if ($resolved === null) {
            throw new AuthorizationException('The authenticated account is not eligible.');
        }

        return $resolved;
    }
}
