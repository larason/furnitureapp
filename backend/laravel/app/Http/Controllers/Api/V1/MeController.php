<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UpdateMeRequest;
use App\Models\User;
use App\Services\UpdateCustomerProfile;
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
        return $this->profileResponse($profile->update($request->user(), $request->validated()));
    }

    private function profileResponse(User $user): JsonResponse
    {
        return response()->json(['data' => [
            'id' => (string) $user->getKey(),
            'role' => $user->getRoleNames()->first(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->email_verified_at !== null,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ]])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ]);
    }
}
