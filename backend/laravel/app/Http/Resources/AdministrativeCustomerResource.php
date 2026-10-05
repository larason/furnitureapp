<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\UserIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class AdministrativeCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        return [
            'id' => UserIdentifier::encodeId($customer->id),
            'role' => 'CUSTOMER',
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'email_verified' => $customer->email_verified_at !== null,
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }
}
