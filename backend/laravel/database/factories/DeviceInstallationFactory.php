<?php

namespace Database\Factories;

use App\Models\DeviceInstallation;
use App\Models\User;
use App\Services\Push\PushTokenCipher;
use App\Services\Push\PushTokenFingerprint;
use App\Support\DevicePlatform;
use App\Support\DeviceRegistrationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeviceInstallation> */
class DeviceInstallationFactory extends Factory
{
    protected $model = DeviceInstallation::class;

    public function definition(): array
    {
        $token = $this->faker->unique()->regexify('[A-Za-z0-9:_-]{140}');

        return [
            'user_id' => User::factory()->customer(),
            'platform' => DevicePlatform::ANDROID,
            'app_instance_id' => $this->faker->unique()->uuid(),
            'token_ciphertext' => app(PushTokenCipher::class)->encrypt($token),
            'token_fingerprint' => app(PushTokenFingerprint::class)->for($token),
            'status' => DeviceRegistrationStatus::ACTIVE,
            'app_version' => '1.0.0',
            'app_build' => '1',
            'last_registered_at' => now(),
            'deactivated_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => DeviceRegistrationStatus::INACTIVE,
            'deactivated_at' => now(),
        ]);
    }

    public function forOwner(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey()]);
    }

    /** Registers a specific plaintext token so uniqueness can be exercised. */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => [
            'token_ciphertext' => app(PushTokenCipher::class)->encrypt($token),
            'token_fingerprint' => app(PushTokenFingerprint::class)->for($token),
            'app_instance_id' => $this->faker->unique()->uuid(),
        ]);
    }
}
