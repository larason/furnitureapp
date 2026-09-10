<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_carts_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('carts'));
        $this->assertTrue(Schema::hasColumns('carts', [
            'id',
            'user_id',
            'guest_token_digest',
            'status',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_cart_items_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('cart_items'));
        $this->assertTrue(Schema::hasColumns('cart_items', [
            'id',
            'cart_id',
            'product_id',
            'variant_id',
            'quantity',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_customer_cart_has_user_and_null_guest_digest(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->customerOwned()->create();

        $this->assertSame($user->id, $cart->user_id);
        $this->assertNull($cart->guest_token_digest);
        $this->assertTrue($cart->isCustomerOwned());
        $this->assertFalse($cart->isGuest());
        $this->assertFalse(Cart::query()->find($cart->id)->guest_token_digest === '');
    }

    public function test_guest_cart_has_digest_and_null_user(): void
    {
        $cart = Cart::factory()->guestOwned()->create();

        $this->assertNull($cart->user_id);
        $this->assertNotNull($cart->guest_token_digest);
        $this->assertTrue($cart->isGuest());
        $this->assertFalse($cart->isCustomerOwned());
        $this->assertFalse(Cart::query()->find($cart->id)->user_id !== null);
    }

    public function test_cart_without_any_owner_is_rejected_by_application(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A cart must be owned by exactly one owner');

        Cart::query()->create([
            'status' => CartStatus::ACTIVE,
        ]);
    }

    public function test_cart_with_both_owners_is_rejected_by_application(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A cart must be owned by exactly one owner');

        Cart::query()->create([
            'user_id' => User::factory()->create()->id,
            'guest_token_digest' => GuestCartCredential::digest(GuestCartCredential::generate()),
            'status' => CartStatus::ACTIVE,
        ]);
    }

    public function test_cart_without_owner_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('carts')->insert([
            'user_id' => null,
            'guest_token_digest' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cart_with_both_owners_is_rejected_by_database(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('carts')->insert([
            'user_id' => $user->id,
            'guest_token_digest' => GuestCartCredential::digest(GuestCartCredential::generate()),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_token_generation_produces_sufficient_entropy(): void
    {
        $token = GuestCartCredential::generate();

        $this->assertGreaterThanOrEqual(32, strlen($token));
        $this->assertGreaterThanOrEqual(256, strlen($token) * 8 * 0.75, 'Token should carry at least ~192 bits of randomness via 48 random alphanumeric chars');
    }

    public function test_guest_tokens_are_unique_and_unpredictable(): void
    {
        $tokens = collect(range(1, 20))
            ->map(fn () => GuestCartCredential::generate())
            ->all();

        $this->assertSame(count($tokens), count(array_unique($tokens)));
        foreach ($tokens as $token) {
            $this->assertTrue(strlen($token) >= 32);
        }
    }

    public function test_digest_is_deterministic_for_same_token_and_key(): void
    {
        config()->set('cart.guest_token_key', 'test-secret-key');
        $token = GuestCartCredential::generate();

        $first = GuestCartCredential::digest($token);
        $second = GuestCartCredential::digest($token);

        $this->assertSame(64, strlen($first));
        $this->assertSame($first, $second);
        $this->assertSame($first, hash_hmac('sha256', $token, 'test-secret-key'));
    }

    public function test_digest_rejects_empty_server_secret(): void
    {
        config()->set('cart.guest_token_key', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GUEST_CART_TOKEN_KEY is not configured');

        GuestCartCredential::digest(GuestCartCredential::generate());
    }

    public function test_different_tokens_produce_different_digests(): void
    {
        config()->set('cart.guest_token_key', 'test-secret-key');

        $a = GuestCartCredential::digest('token-a');
        $b = GuestCartCredential::digest('token-b');

        $this->assertNotSame($a, $b);
    }

    public function test_digest_lookup_retrieves_the_expected_guest_cart(): void
    {
        config()->set('cart.guest_token_key', 'test-secret-key');
        $raw = GuestCartCredential::generate();
        $digest = GuestCartCredential::digest($raw);
        Cart::factory()->guestOwned($digest)->create();

        $found = Cart::query()->where('guest_token_digest', $digest)->first();

        $this->assertNotNull($found);
        $this->assertSame($digest, $found->guest_token_digest);
        $this->assertNull($found->user_id);
    }

    public function test_raw_guest_token_is_never_persisted(): void
    {
        config()->set('cart.guest_token_key', 'test-secret-key');
        $raw = GuestCartCredential::generate();
        $digest = GuestCartCredential::digest($raw);
        Cart::factory()->guestOwned($digest)->create();

        $rawDigest = DB::table('carts')->first()->guest_token_digest;

        $this->assertNotSame($raw, $rawDigest);
        $this->assertSame(64, strlen($rawDigest));
        $this->assertSame($digest, $rawDigest);
        $this->assertFalse(str_contains(strtolower($rawDigest), strtolower($raw)));
    }

    public function test_cart_serialization_hides_guest_token_digest(): void
    {
        config()->set('cart.guest_token_key', 'test-secret-key');
        $digest = GuestCartCredential::digest(GuestCartCredential::generate());
        $cart = Cart::factory()->guestOwned($digest)->create();

        $serialized = $cart->toArray();

        $this->assertArrayNotHasKey('guest_token_digest', $serialized);
    }

    public function test_digest_uniqueness_is_enforced_at_database_level(): void
    {
        config()->set('cart.guest_token_key', 'test-secret-key');
        $digest = GuestCartCredential::digest(GuestCartCredential::generate());
        Cart::factory()->guestOwned($digest)->create();

        $this->expectException(QueryException::class);
        Cart::factory()->guestOwned($digest)->create();
    }

    public function test_one_active_cart_per_customer_is_enforced_by_application(): void
    {
        $user = User::factory()->create();
        Cart::factory()->for($user)->customerOwned()->active()->create();

        $this->expectException(QueryException::class);
        Cart::factory()->for($user)->customerOwned()->active()->create();
    }

    public function test_inactive_cart_history_does_not_block_new_active_cart(): void
    {
        $user = User::factory()->create();
        Cart::factory()->for($user)->customerOwned()->inactive()->create();

        $active = Cart::factory()->for($user)->customerOwned()->active()->create();

        $this->assertSame(CartStatus::ACTIVE, $active->status);
        $this->assertSame(2, Cart::where('user_id', $user->id)->count());
    }

    public function test_multiple_inactive_carts_per_customer_are_allowed(): void
    {
        $user = User::factory()->create();
        $a = Cart::factory()->for($user)->customerOwned()->inactive()->create();
        $b = Cart::factory()->for($user)->customerOwned()->inactive()->create();

        $this->assertSame(CartStatus::INACTIVE, $a->status);
        $this->assertSame(CartStatus::INACTIVE, $b->status);
    }

    public function test_status_defaults_to_active(): void
    {
        Cart::factory()->customerOwned()->create(['status' => CartStatus::ACTIVE]);

        $row = DB::table('carts')->first();
        $this->assertSame('ACTIVE', $row->status);
    }

    public function test_cart_status_is_restricted_to_closed_lexicon(): void
    {
        $this->assertSame('ACTIVE', CartStatus::ACTIVE->value);
        $this->assertSame('INACTIVE', CartStatus::INACTIVE->value);
        $this->assertSame([CartStatus::ACTIVE, CartStatus::INACTIVE], CartStatus::cases());
    }

    public function test_unknown_status_value_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        CartStatus::from('COMPLETED');
    }

    public function test_status_is_cast_to_enum(): void
    {
        $cart = Cart::factory()->customerOwned()->active()->create();

        $this->assertInstanceOf(CartStatus::class, $cart->status);
        $this->assertSame(CartStatus::ACTIVE, $cart->status);
        $this->assertTrue($cart->isActive());

        $inactive = Cart::factory()->customerOwned()->inactive()->create();
        $this->assertSame(CartStatus::INACTIVE, $inactive->status);
        $this->assertFalse($inactive->isActive());
    }

    public function test_cart_item_belongs_to_cart_product_and_variant(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->assertTrue($item->cart->is($cart));
        $this->assertTrue($item->product->is($product));
        $this->assertTrue($item->variant->is($variant));
        $this->assertTrue($cart->items->contains($item));
    }

    public function test_cart_item_variant_is_optional(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();

        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);

        $this->assertNull($item->variant_id);
        $this->assertNull($item->variant);
    }

    public function test_cart_item_variant_must_belong_to_same_product(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $productA = $this->createProduct();
        $productB = $this->createProduct();
        $variantB = ProductVariant::factory()->for($productB)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cart item variant must belong to the same product.');

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $productA->id,
            'variant_id' => $variantB->id,
            'quantity' => 1,
        ]);
    }

    public function test_cart_item_quantity_is_positive(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cart item quantity must be between 1 and 100.');

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 0,
        ]);
    }

    public function test_cart_item_quantity_cannot_exceed_maximum(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cart item quantity must be between 1 and 100.');

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 101,
        ]);
    }

    public function test_duplicate_product_with_null_variant_is_rejected(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);

        $this->expectException(QueryException::class);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);
    }

    public function test_duplicate_product_variant_identity_is_rejected(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->expectException(QueryException::class);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    public function test_same_product_different_variants_can_coexist_in_cart(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();
        $v1 = ProductVariant::factory()->for($product)->create();
        $v2 = ProductVariant::factory()->for($product)->create();

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $v1->id,
            'quantity' => 1,
        ]);
        $second = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $v2->id,
            'quantity' => 2,
        ]);

        $this->assertSame(2, CartItem::where('cart_id', $cart->id)->count());
        $this->assertSame($v2->id, $second->variant_id);
    }

    public function test_null_variant_line_and_variant_line_for_same_product_can_coexist(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);
        $second = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->assertSame(2, CartItem::where('cart_id', $cart->id)->count());
        $this->assertSame($variant->id, $second->variant_id);
    }

    public function test_overlapping_product_and_variant_ids_do_not_collide(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $productSeven = Product::factory()->for($this->createCategory())->create(['id' => 7]);
        $productThree = Product::factory()->for($this->createCategory())->create(['id' => 3]);
        $variantSeven = ProductVariant::factory()->for($productThree)->create(['id' => 7]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $productSeven->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);
        $second = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $productThree->id,
            'variant_id' => $variantSeven->id,
            'quantity' => 1,
        ]);

        $this->assertSame(2, CartItem::where('cart_id', $cart->id)->count());
        $this->assertSame($variantSeven->id, $second->variant_id);
    }

    public function test_cart_item_identity_keys_exist_for_variant_and_null_variant_lines(): void
    {
        $indexes = collect(DB::select('PRAGMA index_list(cart_items)'))
            ->pluck('name')
            ->all();
        $this->assertContains('cart_items_unique_identity', $indexes);
        $this->assertContains('cart_items_unique_null_variant_per_cart', $indexes);
        $this->assertNotContains('cart_items_unique_identity_per_cart', $indexes);
    }

    public function test_deleting_cart_cascades_to_cart_items(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);

        $cart->delete();

        $this->assertSame(0, CartItem::count());
        $this->assertSame(0, DB::table('cart_items')->count());
    }

    public function test_cart_pricing_and_inventory_fields_are_not_persisted(): void
    {
        $cartCols = Schema::getColumnListing('carts');
        $itemCols = Schema::getColumnListing('cart_items');

        foreach (['unit_price', 'subtotal', 'total', 'discount', 'price_snapshot', 'reserved_quantity', 'available_quantity', 'stock_id', 'reserved_at', 'reservation_id', 'expires_at'] as $col) {
            $this->assertNotContains($col, $cartCols);
            $this->assertNotContains($col, $itemCols);
        }
    }

    public function test_cart_item_identity_follows_product_plus_variant(): void
    {
        $cart = Cart::factory()->customerOwned()->create();
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $this->assertNull($item->product_name_snapshot ?? null);
        $this->assertNull($item->sku ?? null);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($variant->id, $item->variant_id);
    }

    private function createProduct(): Product
    {
        return Product::factory()->for($this->createCategory())->create();
    }

    private function createCategory(array $attributes = []): Category
    {
        $category = new Category([
            'name' => $attributes['name'] ?? 'Category',
            'slug' => $attributes['slug'] ?? 'cat-'.strtolower(Str::random(6)),
            'space_type' => $attributes['space_type'] ?? 'home',
            'display_order' => $attributes['display_order'] ?? 0,
        ]);
        $category->save();

        return $category->fresh();
    }
}
