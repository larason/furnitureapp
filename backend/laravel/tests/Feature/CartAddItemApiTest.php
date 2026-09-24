<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Services\Cart\GuestCartTransport;
use App\Support\CartIdentifier;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use App\Support\ProductIdentifier;
use App\Support\ProductType;
use App\Support\VariantIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

class CartAddItemApiTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    private const URL = '/api/v1/me/cart/items';

    public function test_authenticated_add_creates_one_line_with_current_price(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 4500);
        $user = $this->cartCustomer();

        $response = $this->withHeaders($this->authenticateAs($user))
            ->postJson(self::URL, $this->payload($product, $variant, 2))
            ->assertStatus(201)
            ->assertHeaderContains('Cache-Control', 'private');

        $response->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price.amount', 4500)
            ->assertJsonPath('data.items.0.line_total.amount', 9000)
            ->assertJsonPath('data.subtotal.amount', 9000);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(10, $variant->fresh()->stocks->first()->quantity);
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
    }

    public function test_duplicate_add_merges_and_clamps_to_the_maximum(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 200);
        $user = $this->cartCustomer();
        $headers = $this->authenticateAs($user);

        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $variant, 2))->assertStatus(201);
        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $variant, 3))
            ->assertStatus(200)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 5);

        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $variant, 99))
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 100);

        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $variant, 1))
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 100);

        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_same_product_different_variants_are_separate_lines(): void
    {
        [$product, $red] = $this->stockedProduct(quantity: 10);
        $blue = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($blue)->create(['quantity' => 10, 'reserved_quantity' => 0]);

        $user = $this->cartCustomer();
        $headers = $this->authenticateAs($user);

        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $red, 1))->assertStatus(201);
        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $blue, 1))
            ->assertStatus(201)
            ->assertJsonPath('data.items_count', 2);
    }

    public function test_product_and_variant_admission_rules_are_enforced(): void
    {
        $user = $this->cartCustomer();
        $headers = $this->authenticateAs($user);

        [$madeToOrder] = $this->stockedProduct(quantity: 5);
        $madeToOrder->forceFill(['product_type' => ProductType::MADE_TO_ORDER])->save();
        $variant = $madeToOrder->variants()->first();
        $this->withHeaders($headers)->postJson(self::URL, $this->payload($madeToOrder, $variant, 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'PRODUCT_NOT_PURCHASABLE');

        [$inactive] = $this->stockedProduct(quantity: 5);
        $inactive->update(['is_active' => false]);
        $this->withHeaders($headers)->postJson(self::URL, $this->payload($inactive, $inactive->variants()->first(), 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'PRODUCT_UNAVAILABLE');

        [$unpublished] = $this->stockedProduct(quantity: 5);
        $unpublished->forceFill(['is_published' => false])->save();
        $this->withHeaders($headers)->postJson(self::URL, $this->payload($unpublished, $unpublished->variants()->first(), 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'PRODUCT_UNAVAILABLE');

        [$inactiveCategory] = $this->stockedProduct(quantity: 5);
        $inactiveCategory->category->update(['is_active' => false]);
        $this->withHeaders($headers)->postJson(self::URL, $this->payload($inactiveCategory, $inactiveCategory->variants()->first(), 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'PRODUCT_UNAVAILABLE');

        [$inactiveVariantProduct, $inactiveVariant] = $this->stockedProduct(quantity: 5);
        $inactiveVariant->update(['is_active' => false]);
        $this->withHeaders($headers)->postJson(self::URL, $this->payload($inactiveVariantProduct, $inactiveVariant, 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'INVALID_PRODUCT_VARIANT');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_wrong_parent_variant_is_rejected_without_mutation(): void
    {
        [, $variantA] = $this->stockedProduct(quantity: 5);
        [$productB] = $this->stockedProduct(quantity: 5);

        $this->withHeaders($this->authenticateAs($this->cartCustomer()))
            ->postJson(self::URL, $this->payload($productB, $variantA, 1))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_PRODUCT_VARIANT');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_insufficient_and_reserved_stock_are_rejected_without_mutation(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 3);
        $user = $this->cartCustomer();

        $this->withHeaders($this->authenticateAs($user))
            ->postJson(self::URL, $this->payload($product, $variant, 4))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INSUFFICIENT_STOCK');
        $this->assertSame(0, CartItem::query()->count());

        [$fullyReserved, $reservedVariant] = $this->stockedProduct(quantity: 10, reserved: 10);
        $this->withHeaders($this->authenticateAs($user))
            ->postJson(self::URL, $this->payload($fullyReserved, $reservedVariant, 1))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INSUFFICIENT_STOCK');
    }

    public function test_stock_validation_aggregates_all_locations(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 2, location: 'main');
        ProductStock::factory()->forVariant($variant)->create(['warehouse_location' => 'dar-es-salaam', 'quantity' => 3, 'reserved_quantity' => 0]);

        $this->withHeaders($this->authenticateAs($this->cartCustomer()))
            ->postJson(self::URL, $this->payload($product, $variant, 4))
            ->assertStatus(201)
            ->assertJsonPath('data.items.0.quantity', 4);
    }

    public function test_add_does_not_reserve_inventory(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $before = ProductStock::query()->where('product_variant_id', $variant->id)->sole();

        $this->withHeaders($this->authenticateAs($this->cartCustomer()))
            ->postJson(self::URL, $this->payload($product, $variant, 4))
            ->assertStatus(201);

        $after = ProductStock::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame($before->quantity, $after->quantity);
        $this->assertSame($before->reserved_quantity, $after->reserved_quantity);
    }

    public function test_unknown_and_missing_fields_are_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->cartCustomer();
        $headers = $this->authenticateAs($user);

        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $variant, 1) + ['price' => 1])
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'INVALID_VALUE');

        $this->withHeaders($headers)->postJson(self::URL, ['quantity' => 1])
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->withHeaders($headers)->postJson(self::URL, ['product_id' => ProductIdentifier::encode($product)])
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->withHeaders($headers)->postJson(self::URL, $this->payload($product, $variant, 0))
            ->assertUnprocessable();

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_guest_flutter_add_issues_header_and_merges_on_retry(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);

        $first = $this->postJson(self::URL, $this->payload($product, $variant, 1))->assertStatus(201);
        $raw = $first->headers->get(GuestCartTransport::HEADER);
        $this->assertTrue(Str::isUuid((string) $raw));

        $cart = Cart::query()->sole();
        $this->assertSame(GuestCartCredential::digest((string) $raw), $cart->guest_token_digest);

        $this->withHeaders([GuestCartTransport::HEADER => (string) $raw])
            ->postJson(self::URL, $this->payload($product, $variant, 2))
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.id', CartIdentifier::encode($cart));
    }

    public function test_guest_browser_add_issues_cookie_only(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);

        $response = $this->withHeaders(['Origin' => 'https://www.example.com'])
            ->postJson(self::URL, $this->payload($product, $variant, 1))
            ->assertStatus(201);

        $this->assertNull($response->headers->get(GuestCartTransport::HEADER));
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie): bool => $cookie->getName() === GuestCartTransport::COOKIE);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_invalid_guest_credential_does_not_create_a_cart(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);

        $this->withHeaders([GuestCartTransport::HEADER => (string) Str::uuid()])
            ->postJson(self::URL, $this->payload($product, $variant, 1))
            ->assertUnauthorized();

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_authenticated_identity_wins_over_supplied_guest_credential(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $user = $this->cartCustomer();
        $userCart = $this->activeCartFor($user);

        $guestCart = $this->activeCartFor(null);
        $raw = GuestCartCredential::generate();
        $guestCart->update(['guest_token_digest' => GuestCartCredential::digest($raw)]);

        $this->withHeaders($this->authenticateAs($user) + [GuestCartTransport::HEADER => $raw])
            ->postJson(self::URL, $this->payload($product, $variant, 1))
            ->assertStatus(201)
            ->assertJsonPath('data.id', CartIdentifier::encode($userCart));

        $this->assertSame(0, $guestCart->fresh()->items()->count());
    }

    public function test_failed_anonymous_add_does_not_leave_an_unreachable_guest_cart(): void
    {
        [, $variantA] = $this->stockedProduct(quantity: 5);
        [$madeToOrderProduct] = $this->stockedProduct(quantity: 5);
        $madeToOrderProduct->forceFill(['product_type' => ProductType::MADE_TO_ORDER])->save();
        [$productB] = $this->stockedProduct(quantity: 5);
        [$product, $outOfStockVariant] = $this->stockedProduct(quantity: 0);

        $this->postJson(self::URL, $this->payload($madeToOrderProduct, $madeToOrderProduct->variants()->first(), 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'PRODUCT_NOT_PURCHASABLE');
        $this->postJson(self::URL, $this->payload($productB, $variantA, 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'INVALID_PRODUCT_VARIANT');
        $this->postJson(self::URL, $this->payload($product, $outOfStockVariant, 1))
            ->assertUnprocessable()->assertJsonPath('errors.0.code', 'INSUFFICIENT_STOCK');

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_failed_add_on_an_existing_guest_cart_preserves_the_cart(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 0);
        $raw = GuestCartCredential::generate();
        $cart = Cart::factory()->create([
            'user_id' => null,
            'guest_token_digest' => GuestCartCredential::digest($raw),
            'status' => CartStatus::ACTIVE,
        ]);

        $this->withHeaders([GuestCartTransport::HEADER => $raw])
            ->postJson(self::URL, $this->payload($product, $variant, 1))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INSUFFICIENT_STOCK');

        $this->assertSame(1, Cart::query()->count());
        $this->assertNotNull($cart->fresh());
    }

    public function test_product_without_a_variant_is_not_purchasable(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => ProductType::IN_STOCK,
            'is_active' => true,
            'is_published' => true,
        ]);

        $this->postJson(self::URL, ['product_id' => ProductIdentifier::encode($product), 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'PRODUCT_NOT_PURCHASABLE');

        $this->assertSame(0, Cart::query()->count());
    }

    public function test_variant_is_required_when_the_product_defines_variants(): void
    {
        [$product] = $this->stockedProduct(quantity: 10);

        $this->postJson(self::URL, ['product_id' => ProductIdentifier::encode($product), 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_PRODUCT_VARIANT')
            ->assertJsonPath('errors.0.field', 'variant_id');

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    /** @return array<string, mixed> */
    private function payload(Product $product, ?ProductVariant $variant, int $quantity): array
    {
        return [
            'product_id' => ProductIdentifier::encode($product),
            'variant_id' => $variant === null ? null : VariantIdentifier::encode($variant),
            'quantity' => $quantity,
        ];
    }
}
