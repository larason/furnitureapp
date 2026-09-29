<?php

namespace Tests\Feature;

use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Services\Cart\AddCartItem;
use App\Services\Cart\MergeGuestCart;
use App\Services\Cart\RemoveCartItem;
use App\Services\Cart\UpdateCartItemQuantity;
use App\Support\CartStatus;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

class CartMutationGuardTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    public function test_add_rejects_a_cart_that_is_no_longer_active(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $cart = Cart::factory()->guestOwned()->create(['status' => CartStatus::INACTIVE]);

        $exception = $this->capture(fn () => app(AddCartItem::class)->add($cart, $product, $variant, 1));

        $this->assertSame('CONFLICT', $exception->errorCode()->value);
        $this->assertSame(409, $exception->status());
        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_update_rejects_a_cart_that_is_no_longer_active(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $cart = Cart::factory()->guestOwned()->create(['status' => CartStatus::INACTIVE]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $exception = $this->capture(fn () => app(UpdateCartItemQuantity::class)->update($item, 5));

        $this->assertSame('CONFLICT', $exception->errorCode()->value);
        $this->assertSame(409, $exception->status());
        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_remove_rejects_a_cart_that_is_no_longer_active(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $cart = Cart::factory()->guestOwned()->create(['status' => CartStatus::INACTIVE]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $exception = $this->capture(fn () => app(RemoveCartItem::class)->remove($item));

        $this->assertSame('CONFLICT', $exception->errorCode()->value);
        $this->assertSame(409, $exception->status());
        $this->assertNotNull($item->fresh());
    }

    public function test_add_rejects_a_new_line_when_the_cart_is_at_capacity(): void
    {
        [$product] = $this->stockedProduct(quantity: 10);
        $cart = Cart::factory()->guestOwned()->create(['status' => CartStatus::ACTIVE]);
        $this->fillCart($cart, $product);

        $extra = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($extra)->create(['quantity' => 10, 'reserved_quantity' => 0]);

        $exception = $this->capture(fn () => app(AddCartItem::class)->add($cart, $product, $extra, 1));

        $this->assertSame('INVALID_VALUE', $exception->errorCode()->value);
        $this->assertSame(422, $exception->status());
        $this->assertSame(Cart::MAX_ITEMS, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_existing_line_can_still_be_merged_when_the_cart_is_at_capacity(): void
    {
        [$product] = $this->stockedProduct(quantity: 10);
        $cart = Cart::factory()->guestOwned()->create(['status' => CartStatus::ACTIVE]);
        $variants = $this->fillCart($cart, $product);
        $target = $variants->first();
        ProductStock::factory()->forVariant($target)->create(['quantity' => 200, 'reserved_quantity' => 0]);

        $item = app(AddCartItem::class)->add($cart, $product, $target, 1);

        $this->assertSame(2, $item->quantity);
        $this->assertSame(Cart::MAX_ITEMS, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_merge_rejects_lines_beyond_capacity_and_keeps_the_source_active(): void
    {
        [$product] = $this->stockedProduct(quantity: 10);
        $user = $this->cartCustomer();
        $target = $this->activeCartFor($user);
        $this->fillCart($target, $product);

        [$guestProduct, $guestVariant] = $this->stockedProduct(quantity: 10);
        $guest = Cart::factory()->guestOwned()->create(['status' => CartStatus::ACTIVE]);
        CartItem::factory()->create([
            'cart_id' => $guest->id,
            'product_id' => $guestProduct->id,
            'variant_id' => $guestVariant->id,
            'quantity' => 1,
        ]);

        $exception = $this->capture(fn () => app(MergeGuestCart::class)->merge($user, (string) $guest->guest_token_digest));

        $this->assertSame('INVALID_VALUE', $exception->errorCode()->value);
        $this->assertSame(422, $exception->status());
        $this->assertSame(CartStatus::ACTIVE, $guest->fresh()->status);
        $this->assertSame(Cart::MAX_ITEMS, CartItem::query()->where('cart_id', $target->id)->count());
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function fillCart(Cart $cart, Product $product): Collection
    {
        $variants = ProductVariant::factory()->count(Cart::MAX_ITEMS)->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        foreach ($variants as $variant) {
            CartItem::factory()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]);
        }

        return $variants;
    }

    private function capture(Closure $callback): ApiException
    {
        try {
            $callback();
        } catch (ApiException $exception) {
            return $exception;
        }

        $this->fail('Expected an ApiException to be thrown.');
    }
}
