<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\ClerkTokenVerifier;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\GuestCartTransport;
use App\Support\ApiErrorCode;
use App\Support\CartIdentifier;
use App\Support\CartItemIdentifier;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use App\Support\ProductIdentifier;
use App\Support\ProductType;
use App\Support\VariantIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartReadApiTest extends TestCase
{
    use RefreshDatabase;

    private const CART_URL = '/api/v1/me/cart';

    public function test_authenticated_caller_with_existing_cart_gets_the_same_cart(): void
    {
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);

        $response = $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.id', CartIdentifier::encode($cart))
            ->assertJsonPath('data.items', []);

        $this->assertSame(1, Cart::query()->count());
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_authenticated_caller_without_cart_gets_a_lazily_created_empty_cart(): void
    {
        $user = $this->customer();

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.subtotal.amount', 0)
            ->assertJsonPath('data.subtotal.currency', 'TZS')
            ->assertJsonStructure(['data' => ['id', 'items_count', 'items', 'subtotal' => ['amount', 'currency'], 'updated_at']]);

        $cart = Cart::query()->sole();
        $this->assertSame($user->id, $cart->user_id);
        $this->assertNull($cart->guest_token_digest);
        $this->assertSame(CartStatus::ACTIVE, $cart->status);
    }

    public function test_repeated_authenticated_get_returns_the_same_single_cart(): void
    {
        $user = $this->customer();
        $headers = $this->authenticateAs($user);

        $first = $this->withHeaders($headers)->getJson(self::CART_URL)->assertOk()->json('data.id');
        $second = $this->withHeaders($headers)->getJson(self::CART_URL)->assertOk()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Cart::query()->count());
    }

    public function test_flutter_guest_read_does_not_create_or_issue_a_cart(): void
    {
        $response = $this->getJson(self::CART_URL)->assertOk();

        $this->assertNull($response->headers->get(GuestCartTransport::HEADER));
        $this->assertSame([], $response->headers->getCookies());
        $id = $response->json('data.id');
        $this->assertIsString($id);
        $this->assertStringStartsWith('cart_', $id);
        $this->assertNotSame('cart_0', $id);
        $this->assertNotNull($response->json('data.updated_at'));
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_independent_anonymous_reads_return_independent_transient_handles(): void
    {
        $first = $this->getJson(self::CART_URL)->assertOk()->json('data.id');
        $second = $this->getJson(self::CART_URL)->assertOk()->json('data.id');

        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertStringStartsWith('cart_', $first);
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_browser_guest_read_does_not_issue_a_cookie(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://www.example.com'])->getJson(self::CART_URL)->assertOk();

        $this->assertNull($response->headers->get(GuestCartTransport::HEADER));
        $this->assertSame([], $response->headers->getCookies());
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_browser_guest_read_with_fetch_metadata_remains_non_persistent(): void
    {
        $response = $this->withHeaders([
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
        ])->getJson(self::CART_URL)->assertOk();

        $this->assertNull($response->headers->get(GuestCartTransport::HEADER));
        $this->assertSame([], $response->headers->getCookies());
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_guest_with_existing_credential_resolves_the_same_cart_without_reissue(): void
    {
        $raw = GuestCartCredential::generate();
        $cart = Cart::factory()->create([
            'user_id' => null,
            'guest_token_digest' => GuestCartCredential::digest($raw),
            'status' => CartStatus::ACTIVE,
        ]);

        $response = $this->withHeaders([GuestCartTransport::HEADER => $raw])->getJson(self::CART_URL)->assertOk();

        $this->assertSame(CartIdentifier::encode($cart), $response->json('data.id'));
        $this->assertNull($response->headers->get(GuestCartTransport::HEADER));
        $this->assertSame(1, Cart::query()->count());
    }

    public function test_unknown_retired_and_malformed_guest_credentials_are_rejected(): void
    {
        $this->withHeaders([GuestCartTransport::HEADER => (string) Str::uuid()])
            ->getJson(self::CART_URL)
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');

        $this->withHeaders([GuestCartTransport::HEADER => 'not-a-uuid'])
            ->getJson(self::CART_URL)
            ->assertUnauthorized();
    }

    public function test_retired_guest_credential_does_not_reactivate_its_cart(): void
    {
        $raw = GuestCartCredential::generate();
        Cart::factory()->create([
            'user_id' => null,
            'guest_token_digest' => GuestCartCredential::digest($raw),
            'status' => CartStatus::INACTIVE,
        ]);

        $this->withHeaders([GuestCartTransport::HEADER => $raw])
            ->getJson(self::CART_URL)
            ->assertUnauthorized();

        $this->assertSame(CartStatus::INACTIVE, Cart::query()->sole()->status);
    }

    public function test_ambiguous_guest_credential_transport_is_rejected(): void
    {
        $raw = GuestCartCredential::generate();

        $this->withCredentials()
            ->withHeaders([GuestCartTransport::HEADER => $raw])
            ->withUnencryptedCookie(GuestCartTransport::COOKIE, $raw)
            ->getJson(self::CART_URL)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE');
    }

    public function test_authenticated_identity_wins_over_a_guest_credential(): void
    {
        $user = $this->customer();
        $userCart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);

        $raw = GuestCartCredential::generate();
        $guestCart = Cart::factory()->create([
            'user_id' => null,
            'guest_token_digest' => GuestCartCredential::digest($raw),
            'status' => CartStatus::ACTIVE,
        ]);

        $response = $this->withHeaders($this->authenticateAs($user) + [GuestCartTransport::HEADER => $raw])
            ->getJson(self::CART_URL)
            ->assertOk();

        $this->assertSame(CartIdentifier::encode($userCart), $response->json('data.id'));
        $this->assertSame(CartStatus::ACTIVE, $guestCart->fresh()->status);
        $this->assertNull($response->headers->get(GuestCartTransport::HEADER));
    }

    public function test_invalid_bearer_does_not_downgrade_to_a_valid_guest_credential(): void
    {
        $raw = GuestCartCredential::generate();

        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andThrow(
            new ClerkAuthenticationFailure(ApiErrorCode::INVALID_AUTHENTICATION, 'Invalid.', 401),
        );
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        $this->withHeaders(['Authorization' => 'Bearer invalid', GuestCartTransport::HEADER => $raw])
            ->getJson(self::CART_URL)
            ->assertUnauthorized();
    }

    public function test_staff_may_not_use_customer_cart(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'staff_cart', 'account_state' => 'ACTIVE']);

        $this->withHeaders($this->authenticateAs($staff))->getJson(self::CART_URL)
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_holder_selection_ignores_client_supplied_cart_and_user_identifiers(): void
    {
        $user = $this->customer();
        $own = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        $other = Cart::factory()->customerOwned()->create(['status' => CartStatus::ACTIVE]);

        $response = $this->withHeaders($this->authenticateAs($user))
            ->getJson(self::CART_URL.'?cart_id='.CartIdentifier::encode($other).'&user_id='.$other->user_id)
            ->assertOk();

        $this->assertSame(CartIdentifier::encode($own), $response->json('data.id'));
    }

    public function test_existing_cart_projects_current_price_availability_and_purchasability(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 5, reserved: 1);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response = $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)->assertOk();

        $response->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.id', CartItemIdentifier::encode($item))
            ->assertJsonPath('data.items.0.product_id', ProductIdentifier::encode($product))
            ->assertJsonPath('data.items.0.variant_id', VariantIdentifier::encode($variant))
            ->assertJsonPath('data.items.0.unit_price.amount', (int) $variant->price_amount)
            ->assertJsonPath('data.items.0.line_total.amount', (int) $variant->price_amount * 2)
            ->assertJsonPath('data.items.0.availability', 'available')
            ->assertJsonPath('data.items.0.is_purchasable', true)
            ->assertJsonPath('data.subtotal.amount', (int) $variant->price_amount * 2)
            ->assertJsonPath('data.subtotal.currency', 'TZS');

        $stock = ProductStock::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame(5, $stock->fresh()->quantity);
        $this->assertSame(1, $stock->fresh()->reserved_quantity);
    }

    public function test_items_count_counts_distinct_lines_not_quantities(): void
    {
        [$product, $variantA] = $this->stockedProduct(quantity: 10);
        $variantB = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($variantB)->create(['quantity' => 10, 'reserved_quantity' => 0]);

        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variantA->id, 'quantity' => 4]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variantB->id, 'quantity' => 2]);

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 2);
    }

    public function test_price_changes_are_reflected_without_mutating_the_cart(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 1000);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $cartUpdatedAt = $cart->updated_at;

        $variant->update(['price_amount' => 2500]);

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price.amount', 2500)
            ->assertJsonPath('data.items.0.line_total.amount', 2500);

        $this->assertEquals($cartUpdatedAt, $cart->fresh()->updated_at);
    }

    public function test_stale_lines_are_preserved_and_marked_not_purchasable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);

        $product->update(['is_active' => false]);

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.is_purchasable', false);

        $variant->update(['is_active' => false]);
        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 1);
    }

    public function test_out_of_stock_line_is_preserved_and_not_purchasable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 1);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);

        ProductStock::query()->where('product_variant_id', $variant->id)->update(['quantity' => 0, 'reserved_quantity' => 0]);

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.availability', 'unavailable')
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_soft_deleted_product_line_is_still_renderable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);

        $product->delete();

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_cart_response_never_leaks_ownership_or_credential_fields(): void
    {
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->stockedProduct(quantity: 3)[0]->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);

        $response = $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)->assertOk();
        $content = (string) $response->getContent();

        foreach (['guest_token_digest', 'user_id', 'active_user_guard', 'cart_id'] as $needle) {
            $this->assertStringNotContainsString($needle, $content);
        }
    }

    public function test_unpriceable_line_returns_null_price_instead_of_fabricating_zero(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => ProductType::IN_STOCK,
            'is_active' => true,
            'is_published' => true,
        ]);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 2,
        ]);

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.unit_price', null)
            ->assertJsonPath('data.items.0.line_total', null)
            ->assertJsonPath('data.items.0.is_purchasable', false)
            ->assertJsonPath('data.subtotal.amount', 0);
    }

    public function test_variant_less_line_does_not_borrow_another_variants_price(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => ProductType::IN_STOCK,
            'is_active' => true,
            'is_published' => true,
        ]);
        ProductVariant::factory()->inactive()->create([
            'product_id' => $product->id,
            'price_amount' => 7777,
            'price_currency' => 'TZS',
        ]);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 3,
        ]);

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', null)
            ->assertJsonPath('data.items.0.line_total', null)
            ->assertJsonPath('data.items.0.is_purchasable', false)
            ->assertJsonPath('data.subtotal.amount', 0);
    }

    public function test_inactive_variant_line_is_unpriceable_and_not_purchasable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 4400);
        $variant->update(['is_active' => false]);
        $user = $this->customer();
        $cart = Cart::factory()->customerOwned()->create(['user_id' => $user->id, 'status' => CartStatus::ACTIVE]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', null)
            ->assertJsonPath('data.items.0.line_total', null)
            ->assertJsonPath('data.items.0.availability', 'unavailable')
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    private function customer(): User
    {
        return User::factory()->customer()->create(['clerk_user_id' => 'customer_cart']);
    }

    private function authenticateAs(User $user): array
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
    private function stockedProduct(int $quantity, int $price = 5000, int $reserved = 0): array
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
            'warehouse_location' => 'main',
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
        ]);

        return [$product, $variant];
    }
}
