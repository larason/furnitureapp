<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\ProductStock;
use App\Services\Cart\GuestCartTransport;
use App\Support\CartItemIdentifier;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

class CartUpdateItemApiTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    public function test_quantity_can_be_increased_decreased_and_set_to_boundaries(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 300);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 2);
        $headers = $this->authenticateAs($user);
        $url = '/api/v1/me/cart/items/'.CartItemIdentifier::encode($item);

        $this->withHeaders($headers)->patchJson($url, ['quantity' => 5])->assertOk()->assertJsonPath('data.items.0.quantity', 5);
        $this->withHeaders($headers)->patchJson($url, ['quantity' => 2])->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $this->withHeaders($headers)->patchJson($url, ['quantity' => 1])->assertOk()->assertJsonPath('data.items.0.quantity', 1);
        $this->withHeaders($headers)->patchJson($url, ['quantity' => 100])->assertOk()->assertJsonPath('data.items.0.quantity', 100);
    }

    public function test_invalid_quantities_and_unknown_fields_are_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 300);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 2);
        $headers = $this->authenticateAs($user);
        $url = '/api/v1/me/cart/items/'.CartItemIdentifier::encode($item);

        foreach ([0, 101, '2', 1.5, null] as $quantity) {
            $this->withHeaders($headers)->patchJson($url, ['quantity' => $quantity])->assertUnprocessable();
        }

        $this->withHeaders($headers)->patchJson($url, ['quantity' => 2, 'product_id' => 'prod_1'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE');

        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_insufficient_stock_is_rejected_without_mutation_or_timestamp_change(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 3);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 2);

        $itemBefore = $item->fresh()->updated_at;
        $cartBefore = $cart->fresh()->updated_at;
        $this->travel(1)->minutes();

        $this->withHeaders($this->authenticateAs($user))
            ->patchJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item), ['quantity' => 4])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INSUFFICIENT_STOCK');

        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertEquals($itemBefore, $item->fresh()->updated_at);
        $this->assertEquals($cartBefore, $cart->fresh()->updated_at);
        $this->assertSame(3, ProductStock::query()->where('product_variant_id', $variant->id)->sole()->quantity);
        $this->assertSame(0, ProductStock::query()->where('product_variant_id', $variant->id)->sole()->reserved_quantity);
    }

    public function test_successful_update_touches_the_parent_cart(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 1);

        $before = $cart->fresh()->updated_at;
        $this->travel(1)->minutes();

        $this->withHeaders($this->authenticateAs($user))
            ->patchJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item), ['quantity' => 3])
            ->assertOk();

        $this->assertTrue($cart->fresh()->updated_at->greaterThan($before));
    }

    public function test_same_quantity_is_an_idempotent_no_op(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 3);

        $before = $cart->fresh()->updated_at;
        $this->travel(1)->minutes();

        $this->withHeaders($this->authenticateAs($user))
            ->patchJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item), ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertEquals($before, $cart->fresh()->updated_at);
    }

    public function test_cross_holder_and_unknown_items_are_masked_as_not_found(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $owner = $this->cartCustomer('owner_cart');
        $intruder = $this->cartCustomer('intruder_cart');
        $cart = $this->activeCartFor($owner);
        $item = $this->itemFor($cart, $product, $variant, 2);

        $this->withHeaders($this->authenticateAs($intruder))
            ->patchJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item), ['quantity' => 5])
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'CART_ITEM_NOT_FOUND');

        $this->withHeaders($this->authenticateAs($owner))
            ->patchJson('/api/v1/me/cart/items/item_zzzzz', ['quantity' => 5])
            ->assertNotFound();

        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_stale_item_update_fails_but_stays_removable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $product, $variant, 2);
        $headers = $this->authenticateAs($user);

        $product->update(['is_active' => false]);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item), ['quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'PRODUCT_UNAVAILABLE');

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item))
            ->assertNoContent();
    }

    public function test_guest_can_update_their_own_bound_cart(): void
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
            ->patchJson('/api/v1/me/cart/items/'.CartItemIdentifier::encode($item), ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4);
    }
}
