<?php

namespace Database\Factories;

use App\Models\FurnitureRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\RequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FurnitureRequest>
 */
class FurnitureRequestFactory extends Factory
{
    private static array $usedReferences = [];

    public function definition(): array
    {
        return [
            'user_id' => null,
            'request_reference' => self::generateReference(),
            'product_id' => null,
            'product_details' => self::productDetails(),
            'style' => fake()->randomElement(['Modern', 'Minimalist', 'Scandinavian', 'Classic', 'Industrial']),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2557'.fake()->numerify('########'),
            'message' => fake()->paragraph(),
            'quantity' => null,
            'dimensions' => null,
            'material' => null,
            'color' => null,
            'request_status' => RequestStatus::SUBMITTED,
            'staff_internal_notes' => null,
        ];
    }

    public function guest(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
        ]);
    }

    public function byUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
        ]);
    }

    public function withDimensions(): static
    {
        return $this->state(fn () => [
            'dimensions' => [
                'length' => fake()->numberBetween(50, 3000),
                'width' => fake()->numberBetween(50, 3000),
                'height' => fake()->numberBetween(50, 3000),
                'unit' => 'cm',
            ],
        ]);
    }

    public function withMaterialAndColor(): static
    {
        return $this->state(fn () => [
            'material' => fake()->randomElement(['Oak', 'Linen', 'Leather', 'Metal']),
            'color' => fake()->safeColorName(),
        ]);
    }

    public function withAllSpecs(): static
    {
        return $this->state(fn () => [
            'quantity' => fake()->numberBetween(1, 100),
            'dimensions' => [
                'length' => fake()->numberBetween(50, 3000),
                'width' => fake()->numberBetween(50, 3000),
                'height' => fake()->numberBetween(50, 3000),
                'unit' => 'cm',
            ],
            'material' => fake()->randomElement(['Oak', 'Linen', 'Leather', 'Metal']),
            'color' => fake()->safeColorName(),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => [
            'request_status' => RequestStatus::IN_REVIEW,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'request_status' => RequestStatus::CLOSED,
        ]);
    }

    public static function generateReference(): string
    {
        do {
            $reference = FurnitureRequest::REFERENCE_PREFIX.strtoupper(fake()->bothify('####??????'));
        } while (isset(self::$usedReferences[$reference]));

        self::$usedReferences[$reference] = true;

        return $reference;
    }

    private static function productDetails(): array
    {
        return [
            'product_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'reference' => fake()->optional()->word(),
        ];
    }
}
