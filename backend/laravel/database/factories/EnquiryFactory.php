<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\EnquiryCategory;
use App\Support\EnquiryStatus;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    private static array $usedReferences = [];

    public function definition(): array
    {
        return [
            'user_id' => null,
            'enquiry_reference' => self::generateReference(),
            'product_id' => null,
            'order_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2557'.fake()->numerify('########'),
            'category' => fake()->randomElement(EnquiryCategory::cases()),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'enquiry_status' => EnquiryStatus::OPEN,
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

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
        ]);
    }

    public function general(): static
    {
        return $this->state(fn () => [
            'product_id' => null,
            'order_id' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'enquiry_status' => EnquiryStatus::CLOSED,
        ]);
    }

    public function withStaffNote(): static
    {
        return $this->state(fn () => [
            'staff_internal_notes' => fake()->sentence(),
        ]);
    }

    public static function generateReference(): string
    {
        do {
            $reference = ReferenceGenerator::generate(Enquiry::REFERENCE_PREFIX, 10);
        } while (isset(self::$usedReferences[$reference]));

        self::$usedReferences[$reference] = true;

        return $reference;
    }
}
