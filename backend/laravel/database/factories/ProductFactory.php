<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Support\AssemblyRequired;
use App\Support\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $category = Category::query()->inRandomOrder()->first()
            ?? Category::create([
                'name' => 'General',
                'slug' => 'general-'.strtolower(Str::random(6)),
                'space_type' => 'hybrid',
            ]);

        return [
            'category_id' => $category->id,
            'name' => fake()->unique()->words(3, true),
            'slug' => fn (array $attributes) => Str::slug($attributes['name']),
            'sku_prefix' => null,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'brand' => null,
            'room_type' => null,
            'assembly_required' => AssemblyRequired::NONE,
            'primary_material' => null,
            'is_active' => true,
            'product_type' => ProductType::IN_STOCK,
            'is_published' => true,
            'is_featured' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function inStock(): static
    {
        return $this->state(['product_type' => ProductType::IN_STOCK]);
    }

    public function madeToOrder(): static
    {
        return $this->state(['product_type' => ProductType::MADE_TO_ORDER]);
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }
}
