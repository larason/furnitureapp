<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\ProductStock;
use App\Services\Cart\GuestCartTransport;
use App\Support\CartItemIdentifier;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use App\Support\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

class CartRemoveItemApiTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    public function test_removing_the_last_item_leaves_an_empty_active_cart(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 2);

        $before = $cart->fresh()->updated_at;
        $this->travel(1)->minutes();

        $this->withHeaders($this->authenticateAs($user))
            ->deleteJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item))
            ->assertNoContent();

        $fresh = $cart->fresh();
        $this->assertSame(CartStatus::ACTIVE, $fresh->status);
        $this->assertSame(0, $fresh->items()->count());
        $this->assertTrue($fresh->updated_at->greaterThan($before));

        $this->withHeaders($this->authenticateAs($user))->getJson('/api/v1/me/cart')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.subtotal.amount', 0);
    }

    public function test_stale_items_can_always_be_removed(): void
    {
        $user = $this->cartCustomer();
        $headers = $this->authenticateAs($user);
        $cart = $this->activeCartFor($user);

        [$inactiveProduct, $inactiveProductVariant] = $this->stockedProduct(quantity: 5);
        $inactiveProduct->update(['is_active' => false]);
        $a = $this->itemFor($cart, $inactiveProduct, $inactiveProductVariant, 1);

        [$product, $inactiveVariant] = $this->stockedProduct(quantity: 5);
        $inactiveVariant->update(['is_active' => false]);
        $b = $this->itemFor($cart, $product, $inactiveVariant, 1);

        [$outOfStockProduct, $outOfStockVariant] = $this->stockedProduct(quantity: 1);
        $c = $this->itemFor($cart, $outOfStockProduct, $outOfStockVariant, 1);
        ProductStock::query()->where('product_variant_id', $outOfStockVariant->id)->update(['quantity' => 0, 'reserved_quantity' => 0]);

        [$madeToOrder, $madeToOrderVariant] = $this->stockedProduct(quantity: 5);
        $madeToOrder->forceFill(['product_type' => ProductType::MADE_TO_ORDER])->save();
        $d = $this->itemFor($cart, $madeToOrder, $madeToOrderVariant, 1);

        foreach ([$a, $b, $c, $d] as $item) {
            $this->withHeaders($headers)->deleteJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item))->assertNoContent();
        }

        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_cross_holder_and_unknown_removal_are_masked_as_not_found(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $owner = $this->cartCustomer('owner_remove');
        $intruder = $this->cartCustomer('intruder_remove');
        $cart = $this->activeCartFor($owner);
        $item = $this->itemFor($cart, $product, $variant, 1);

        $this->withHeaders($this->authenticateAs($intruder))
            ->deleteJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item))
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'CART_ITEM_NOT_FOUND');

        $this->withHeaders($this->authenticateAs($owner))
            ->deleteJson('/api/v1/me/cart/items/item_zzzzz')
            ->assertNotFound();

        $this->assertSame(1, $cart->fresh()->items()->count());
    }

    public function test_removal_does_not_touch_inventory(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, reserved: 2);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 1);

        $this->withHeaders($this->authenticateAs($user))
            ->deleteJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item))
            ->assertNoContent();

        $stock = ProductStock::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame(10, $stock->quantity);
        $this->assertSame(2, $stock->reserved_quantity);
    }

    public function test_guest_can_remove_from_their_bound_cart(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $raw = GuestCartCredential::generate();
        $cart = Cart::factory()->create([
            'user_id' => null,
            'guest_token_digest' => GuestCartCredential::digest($raw),
            'status' => CartStatus::ACTIVE,
        ]);
        $item = $this->itemFor($cart, $product, $variant, 1);

        $this->withHeaders([GuestCartTransport::HEADER => $raw])
            ->deleteJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item))
            ->assertNoContent();

        $this->assertSame(0, $cart->fresh()->items()->count());
    }
}
