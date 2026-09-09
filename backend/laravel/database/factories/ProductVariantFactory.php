<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => fn (array $attributes) => 'SKU-'.Str::random(12),
            'variant_name' => fake()->unique()->words(3, true),
            'price_amount' => fake()->numberBetween(10000, 50000000),
            'price_currency' => 'TZS',
            'compare_at_price_amount' => null,
            'compare_at_price_currency' => null,
            'cost_price_amount' => null,
            'cost_price_currency' => null,
            'width_cm' => fake()->numberBetween(10, 300),
            'height_cm' => fake()->numberBetween(10, 300),
            'depth_cm' => fake()->numberBetween(10, 300),
            'weight_kg' => fake()->numberBetween(1, 500),
            'attributes' => null,
            'is_default' => false,
            'is_active' => true,
            'display_order' => 0,
        ];
    }

    public function asDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
