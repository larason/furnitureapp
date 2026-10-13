<?php

namespace App\Http\Resources;

use App\Models\DeviceInstallation;
use App\Support\DeviceInstallationIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a registered device installation.
 *
 * The token, its ciphertext, and its fingerprint are deliberately absent: a
 * client has no reason to receive them, and echoing a token would widen the
 * blast radius of a leaked response.
 *
 * @property-read DeviceInstallation $resource
 */
final class DeviceInstallationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $device = $this->resource;

        return [
            'id' => DeviceInstallationIdentifier::encodeId((int) $device->getKey()),
            'platform' => $device->platform->value,
            'app_instance_id' => $device->app_instance_id,
            'app_version' => $device->app_version,
            'app_build' => $device->app_build,
            'status' => $device->status->value,
            'created_at' => $device->created_at->toISOString(),
            'updated_at' => $device->updated_at->toISOString(),
            'last_registered_at' => $device->last_registered_at?->toISOString(),
        ];
    }
}
