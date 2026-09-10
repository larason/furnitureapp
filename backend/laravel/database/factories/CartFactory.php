<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'guest_token_digest' => null,
            'status' => CartStatus::ACTIVE,
        ];
    }

    public function customerOwned(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $attributes['user_id'] ?? User::factory(),
            'guest_token_digest' => null,
        ]);
    }

    public function guestOwned(?string $digest = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_token_digest' => $digest ?? GuestCartCredential::digest(GuestCartCredential::generate()),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CartStatus::ACTIVE,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CartStatus::INACTIVE,
        ]);
    }
}
