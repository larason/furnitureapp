<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\GuestCartTransport;
use App\Support\CartIdentifier;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

/**
 * CART-005 guest→authenticated merge (Phase 6.8): consolidation, retirement,
 * credential transport, and idempotent replay after the source is retired.
 */
class CartMergeApiTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    private const URL = '/api/v1/me/cart/merge';

    public function test_merge_requires_an_authenticated_principal(): void
    {
        [$guest, $raw] = $this->guestCart([[null, null, 1]]);

        $this->withHeaders([GuestCartTransport::HEADER => $raw, 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson(self::URL)
            ->assertUnauthorized();

        $this->assertSame(CartStatus::ACTIVE, $guest->fresh()->status);
    }

    public function test_merge_requires_a_guest_credential(): void
    {
        $user = $this->cartCustomer();

        $this->withHeaders($this->mergeHeaders($user, idempotencyKey: (string) Str::uuid()))
            ->postJson(self::URL)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->assertSame(0, Cart::query()->where('user_id', $user->id)->count());
    }

    public function test_merge_requires_an_idempotency_key(): void
    {
        [, $raw] = $this->guestCart([[null, null, 1]]);

        $this->withHeaders($this->authenticateAs($this->cartCustomer()) + [GuestCartTransport::HEADER => $raw])
            ->postJson(self::URL)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
    }

    public function test_merge_rejects_a_malformed_idempotency_key(): void
    {
        [$guest, $raw] = $this->guestCart([[null, null, 1]]);

        $this->withHeaders($this->authenticateAs($this->cartCustomer()) + [GuestCartTransport::HEADER => $raw, 'Idempotency-Key' => 'not-a-uuid'])
            ->postJson(self::URL)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_FORMAT');

        $this->assertSame(CartStatus::ACTIVE, $guest->fresh()->status);
    }

    public function test_merge_rejects_ambiguous_guest_transport(): void
    {
        [, $raw] = $this->guestCart([[null, null, 1]]);

        $this->withCredentials()
            ->withUnencryptedCookie(GuestCartTransport::COOKIE, $raw)
            ->withHeaders($this->mergeHeaders($this->cartCustomer(), $raw, (string) Str::uuid()))
            ->postJson(self::URL)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE');
    }

    public function test_merge_rejects_an_unknown_guest_credential(): void
    {
        $this->withHeaders($this->mergeHeaders($this->cartCustomer(), (string) Str::uuid(), (string) Str::uuid()))
            ->postJson(self::URL)
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_merge_copies_guest_lines_into_an_empty_target_and_retires_the_source(): void
    {
        [$productA, $variantA] = $this->stockedProduct(quantity: 50);
        [$productB, $variantB] = $this->stockedProduct(quantity: 50);
        [$guest, $raw] = $this->guestCart([[$productA, $variantA, 2], [$productB, $variantB, 4]]);

        $user = $this->cartCustomer();
        $response = $this->merge($user, $raw, (string) Str::uuid())->assertOk();

        $target = Cart::query()->where('user_id', $user->id)->sole();
        $response->assertJsonPath('data.id', CartIdentifier::encode($target))
            ->assertJsonPath('data.items_count', 2);

        $this->assertSame(2, $this->lineQuantity($target, $variantA));
        $this->assertSame(4, $this->lineQuantity($target, $variantB));

        $this->assertSame(CartStatus::INACTIVE, $guest->fresh()->status);
        $this->assertNotNull($guest->fresh()->guest_token_digest);
        $this->assertSame(2, $guest->fresh()->items()->count());
    }

    public function test_merge_consolidates_overlapping_lines_and_clamps_to_maximum(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 200);
        $user = $this->cartCustomer();
        $target = $this->activeCartFor($user);
        $this->itemFor($target, $product, $variant, 4);

        [, $raw] = $this->guestCart([[$product, $variant, 3]]);
        $this->merge($user, $raw, (string) Str::uuid())->assertOk();

        $this->assertSame(7, $this->lineQuantity($target, $variant));
        $this->assertSame(1, CartItem::query()->where('cart_id', $target->id)->count());

        [$clampProduct, $clampVariant] = $this->stockedProduct(quantity: 200);
        $this->itemFor($target, $clampProduct, $clampVariant, 80);
        [, $clampRaw] = $this->guestCart([[$clampProduct, $clampVariant, 40]]);
        $this->merge($user, $clampRaw, (string) Str::uuid())->assertOk();

        $this->assertSame(100, $this->lineQuantity($target, $clampVariant));
    }

    public function test_merge_keeps_same_product_different_variants_as_separate_lines(): void
    {
        [$product, $red] = $this->stockedProduct(quantity: 50);
        $blue = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($blue)->create(['warehouse_location' => 'main', 'quantity' => 50, 'reserved_quantity' => 0]);

        $user = $this->cartCustomer();
        $target = $this->activeCartFor($user);
        $this->itemFor($target, $product, $blue, 1);

        [, $raw] = $this->guestCart([[$product, $red, 2]]);
        $this->merge($user, $raw, (string) Str::uuid())->assertOk();

        $this->assertSame(1, $this->lineQuantity($target, $blue));
        $this->assertSame(2, $this->lineQuantity($target, $red));
        $this->assertSame(2, CartItem::query()->where('cart_id', $target->id)->count());
    }

    public function test_merge_of_an_empty_guest_cart_is_safe_and_retires_the_source(): void
    {
        [$guest, $raw] = $this->guestCart([]);
        $user = $this->cartCustomer();
        $target = $this->activeCartFor($user);
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $this->itemFor($target, $product, $variant, 3);

        $this->merge($user, $raw, (string) Str::uuid())
            ->assertOk()
            ->assertJsonPath('data.items_count', 1);

        $this->assertSame(3, $this->lineQuantity($target, $variant));
        $this->assertSame(CartStatus::INACTIVE, $guest->fresh()->status);
    }

    public function test_merge_preserves_stale_guest_lines_as_unpurchasable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $product->update(['is_active' => false]);
        [, $raw] = $this->guestCart([[$product, $variant, 5]]);

        $user = $this->cartCustomer();
        $response = $this->merge($user, $raw, (string) Str::uuid())->assertOk();

        $response->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_merge_does_not_touch_inventory(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, reserved: 2);
        [, $raw] = $this->guestCart([[$product, $variant, 3]]);

        $before = ProductStock::query()->where('product_variant_id', $variant->id)->sole();

        $this->merge($this->cartCustomer(), $raw, (string) Str::uuid())->assertOk();

        $after = ProductStock::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame($before->quantity, $after->quantity);
        $this->assertSame($before->reserved_quantity, $after->reserved_quantity);
    }

    public function test_merge_replays_the_same_result_after_the_source_is_retired(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 50);
        [$guest, $raw] = $this->guestCart([[$product, $variant, 3]]);
        $user = $this->cartCustomer();
        $key = (string) Str::uuid();

        $first = $this->merge($user, $raw, $key)->assertOk();
        $this->assertSame(CartStatus::INACTIVE, $guest->fresh()->status);

        $second = $this->merge($user, $raw, $key)->assertOk();

        $second->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 3);

        $target = Cart::query()->where('user_id', $user->id)->sole();
        $this->assertSame(1, CartItem::query()->where('cart_id', $target->id)->count());
        $this->assertSame(3, $this->lineQuantity($target, $variant));
    }

    public function test_merge_same_key_with_a_different_source_conflicts(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 50);
        [, $firstRaw] = $this->guestCart([[$product, $variant, 1]]);
        [, $secondRaw] = $this->guestCart([[$product, $variant, 1]]);
        $user = $this->cartCustomer();
        $key = (string) Str::uuid();

        $this->merge($user, $firstRaw, $key)->assertOk();

        $this->merge($user, $secondRaw, $key)
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'DUPLICATE_OPERATION');
    }

    public function test_merge_idempotency_keys_are_scoped_per_user(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 50);
        [, $rawOne] = $this->guestCart([[$product, $variant, 1]]);
        [, $rawTwo] = $this->guestCart([[$product, $variant, 2]]);
        $key = (string) Str::uuid();

        $this->merge($this->cartCustomer('merge_user_one'), $rawOne, $key)->assertOk();
        $this->merge($this->cartCustomer('merge_user_two'), $rawTwo, $key)->assertOk();
    }

    public function test_merge_supports_the_browser_cookie_transport(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 50);
        [$guest, $raw] = $this->guestCart([[$product, $variant, 2]]);
        $user = $this->cartCustomer();

        $this->withCredentials()
            ->withUnencryptedCookie(GuestCartTransport::COOKIE, $raw)
            ->withHeaders($this->mergeHeaders($user, idempotencyKey: (string) Str::uuid()))
            ->postJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 1);

        $this->assertSame(CartStatus::INACTIVE, $guest->fresh()->status);
    }

    public function test_merge_creates_the_target_cart_when_the_user_has_none(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 50);
        [, $raw] = $this->guestCart([[$product, $variant, 2]]);
        $user = $this->cartCustomer();

        $this->merge($user, $raw, (string) Str::uuid())->assertOk()->assertJsonPath('data.items_count', 1);

        $target = Cart::query()->where('user_id', $user->id)->sole();
        $this->assertSame(2, $this->lineQuantity($target, $variant));
    }

    public function test_retired_guest_credential_cannot_resolve_a_guest_cart(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 50);
        [, $raw] = $this->guestCart([[$product, $variant, 1]]);

        $this->merge($this->cartCustomer(), $raw, (string) Str::uuid())->assertOk();

        $this->flushHeaders();
        app('auth')->forgetGuards();
        $this->withHeaders([GuestCartTransport::HEADER => $raw])
            ->getJson('/api/v1/me/cart')
            ->assertUnauthorized();
    }

    public function test_merge_response_does_not_leak_the_guest_credential(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 50);
        [, $raw] = $this->guestCart([[$product, $variant, 1]]);

        $response = $this->merge($this->cartCustomer(), $raw, (string) Str::uuid())->assertOk();
        $content = (string) $response->getContent();

        foreach ([$raw, GuestCartCredential::digest($raw), 'guest_token_digest', 'guest_cart_id'] as $needle) {
            $this->assertStringNotContainsString($needle, $content);
        }
    }

    public function test_full_guest_to_authenticated_merge_journey(): void
    {
        [$productA, $variantA] = $this->stockedProduct(quantity: 50);
        [$productB, $variantB] = $this->stockedProduct(quantity: 50);

        $this->flushHeaders();
        $firstAdd = $this->postJson('/api/v1/me/cart/items', [
            'product_id' => ProductIdentifier::encode($productA),
            'variant_id' => VariantIdentifier::encode($variantA),
            'quantity' => 2,
        ])->assertStatus(201);
        $raw = $firstAdd->headers->get(GuestCartTransport::HEADER);
        $this->assertNotNull($raw);

        $guestHeaders = [GuestCartTransport::HEADER => (string) $raw];

        $this->withHeaders($guestHeaders)->postJson('/api/v1/me/cart/items', [
            'product_id' => ProductIdentifier::encode($productB),
            'variant_id' => VariantIdentifier::encode($variantB),
            'quantity' => 1,
        ])->assertStatus(201);

        $firstItemId = $this->withHeaders($guestHeaders)->getJson('/api/v1/me/cart')->json('data.items.0.id');

        $this->withHeaders($guestHeaders)
            ->patchJson('/api/v1/me/cart/items/'.$firstItemId, ['quantity' => 5])
            ->assertOk();

        $this->withHeaders($guestHeaders)->getJson('/api/v1/me/cart')->assertJsonPath('data.items_count', 2);

        $stockBefore = ProductStock::query()->where('product_variant_id', $variantA->id)->sole();

        $user = $this->cartCustomer();
        $this->flushHeaders();
        $this->withHeaders($this->mergeHeaders($user, (string) $raw, (string) Str::uuid()))
            ->postJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 2);

        $target = Cart::query()->where('user_id', $user->id)->sole();
        $this->assertSame(5, $this->lineQuantity($target, $variantA));
        $this->assertSame(1, $this->lineQuantity($target, $variantB));
        $this->assertSame(CartStatus::INACTIVE, Cart::query()->where('guest_token_digest', GuestCartCredential::digest((string) $raw))->sole()->status);

        $stockAfter = ProductStock::query()->where('product_variant_id', $variantA->id)->sole();
        $this->assertSame($stockBefore->quantity, $stockAfter->quantity);
        $this->assertSame($stockBefore->reserved_quantity, $stockAfter->reserved_quantity);

        $this->flushHeaders();
        app('auth')->forgetGuards();
        $this->withHeaders($guestHeaders)->getJson('/api/v1/me/cart')->assertUnauthorized();
    }

    /** @return array{0: Cart, 1: string} */
    private function guestCart(array $lines): array
    {
        $raw = GuestCartCredential::generate();
        $cart = Cart::factory()->create([
            'user_id' => null,
            'guest_token_digest' => GuestCartCredential::digest($raw),
            'status' => CartStatus::ACTIVE,
        ]);

        foreach ($lines as [$product, $variant, $quantity]) {
            if ($product instanceof Product && $variant instanceof ProductVariant) {
                $this->itemFor($cart, $product, $variant, $quantity);
            }
        }

        return [$cart, $raw];
    }

    /** @return array<string, string> */
    private function mergeHeaders(User $user, ?string $raw = null, ?string $idempotencyKey = null): array
    {
        $headers = $this->authenticateAs($user);

        if ($raw !== null) {
            $headers[GuestCartTransport::HEADER] = $raw;
        }

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    private function merge(User $user, string $raw, string $key): TestResponse
    {
        return $this->withHeaders($this->mergeHeaders($user, $raw, $key))->postJson(self::URL);
    }

    private function lineQuantity(Cart $cart, ProductVariant $variant): int
    {
        return (int) CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('variant_id', $variant->id)
            ->sole()
            ->quantity;
    }
}
