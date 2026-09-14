<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $unitPrice = fake()->numberBetween(1000, 500000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'sku' => 'SKU-'.fake()->unique()->bothify('????-####'),
            'name' => fake()->words(3, true),
            'variant_name' => null,
            'unit_price_amount' => $unitPrice,
            'quantity' => $quantity,
            'line_total_amount' => $unitPrice * $quantity,
        ];
    }

    public function forVariant(ProductVariant $variant, Product $product): static
    {
        $unitPrice = (int) $variant->price_amount;
        $quantity = fake()->numberBetween(1, 100);

        return $this->state(fn (array $attributes) => [
            'order_id' => $attributes['order_id'] ?? Order::factory(),
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name' => $product->name,
            'variant_name' => $variant->variant_name,
            'unit_price_amount' => $unitPrice,
            'quantity' => $quantity,
            'line_total_amount' => $unitPrice * $quantity,
        ]);
    }

    public function withLineTotal(?int $unitPrice = null, ?int $quantity = null): static
    {
        $qty = $quantity ?? fake()->numberBetween(1, 100);
        $unit = $unitPrice ?? fake()->numberBetween(1000, 500000);

        return $this->state(fn (array $attributes) => [
            'unit_price_amount' => $unit,
            'quantity' => $qty,
            'line_total_amount' => $unit * $qty,
        ]);
    }
}
