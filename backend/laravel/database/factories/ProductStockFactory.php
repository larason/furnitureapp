<?php

namespace Database\Factories;

use App\Models\ProductStock;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStock>
 */
class ProductStockFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 500);

        return [
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_location' => ProductStock::DEFAULT_LOCATION,
            'quantity' => $quantity,
            'reserved_quantity' => fake()->numberBetween(0, $quantity),
        ];
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn (array $attributes) => [
            'product_variant_id' => $variant->id,
        ]);
    }

    public function atLocation(string $location): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_location' => $location,
        ]);
    }
}
