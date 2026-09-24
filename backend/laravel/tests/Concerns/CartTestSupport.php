<?php

namespace Tests\Concerns;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\ClerkTokenVerifier;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CartStatus;
use App\Support\ProductType;

trait CartTestSupport
{
    protected function cartCustomer(string $clerkUserId = 'customer_cart'): User
    {
        return User::factory()->customer()->create(['clerk_user_id' => $clerkUserId]);
    }

    /** @return array<string, string> */
    protected function authenticateAs(User $user): array
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            $user->clerk_user_id,
            'sess_test',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return ['Authorization' => 'Bearer session-token'];
    }

    /** @return array{0: Product, 1: ProductVariant} */
    protected function stockedProduct(int $quantity, int $price = 5000, int $reserved = 0, string $location = 'main'): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => ProductType::IN_STOCK,
            'is_active' => true,
            'is_published' => true,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_amount' => $price,
            'price_currency' => 'TZS',
            'is_active' => true,
        ]);
        ProductStock::factory()->forVariant($variant)->create([
            'warehouse_location' => $location,
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
        ]);

        return [$product, $variant];
    }

    protected function activeCartFor(?User $user): Cart
    {
        return $user === null
            ? Cart::factory()->guestOwned()->create(['status' => CartStatus::ACTIVE])
            : Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
    }

    protected function itemFor(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity): CartItem
    {
        return CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]);
    }
}
