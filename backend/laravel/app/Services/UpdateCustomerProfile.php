<?php

namespace App\Services;

use App\Models\User;

final class UpdateCustomerProfile
{
    /** @param array{name?: string, phone?: string|null} $attributes */
    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes);

        if ($user->isDirty()) {
            $user->save();
        }

        return $user->refresh();
    }
}
