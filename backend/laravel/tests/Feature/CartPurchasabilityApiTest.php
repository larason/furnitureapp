<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Support\CartItemIdentifier;
use App\Support\ProductIdentifier;
use App\Support\ProductType;
use App\Support\VariantIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

/**
 * Phase 6.6 quantity-aware Cart purchasability: coarse Group E `availability`
 * is independent from the line-level `is_purchasable` decision.
 */
class CartPurchasabilityApiTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    private const CART_URL = '/api/v1/me/cart';

    private const ITEMS_URL = '/api/v1/me/cart/items';

    /** @var array<string, string> */
    private array $headers = [];

    public function test_partially_stocked_line_keeps_coarse_availability_but_is_not_purchasable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 2);
        $this->line($product, $variant, 5);

        $this->getCart()
            ->assertJsonPath('data.items.0.availability', 'available')
            ->assertJsonPath('data.items.0.is_purchasable', false)
            ->assertJsonPath('data.items.0.quantity', 5);
    }

    public function test_exact_stock_boundary_is_purchasable_and_one_less_is_not(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 5);
        $this->line($product, $variant, 5);

        $this->getCart()->assertJsonPath('data.items.0.is_purchasable', true);

        $this->setStock($variant, 4);
        $this->getCart()
            ->assertJsonPath('data.items.0.availability', 'available')
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_reserved_stock_is_excluded_from_the_quantity_decision(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, reserved: 6);
        $this->line($product, $variant, 5);

        $this->getCart()
            ->assertJsonPath('data.items.0.availability', 'available')
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_multi_location_availability_is_aggregated_for_purchasability(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 2, location: 'main');
        ProductStock::factory()->forVariant($variant)->create(['warehouse_location' => 'second', 'quantity' => 3, 'reserved_quantity' => 0]);
        $this->line($product, $variant, 5);

        $this->getCart()->assertJsonPath('data.items.0.is_purchasable', true);

        ProductStock::query()->where('warehouse_location', 'second')->update(['quantity' => 2]);
        $this->getCart()->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_low_stock_indicator_does_not_block_purchasability(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 4);
        $this->line($product, $variant, 2);

        $this->getCart()
            ->assertJsonPath('data.items.0.stock_indicator', 'LOW_STOCK')
            ->assertJsonPath('data.items.0.is_purchasable', true);
    }

    public function test_stock_recovery_restores_purchasability_without_cart_mutation(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 2);
        $item = $this->line($product, $variant, 5);

        $this->getCart()->assertJsonPath('data.items.0.is_purchasable', false);

        $cartUpdatedAt = $item->cart->fresh()->updated_at?->toISOString();
        $itemUpdatedAt = $item->fresh()->updated_at?->toISOString();

        $this->setStock($variant, 5);

        $this->getCart()->assertJsonPath('data.items.0.is_purchasable', true);

        $this->assertSame(5, $item->fresh()->quantity);
        $this->assertSame($itemUpdatedAt, $item->fresh()->updated_at?->toISOString());
        $this->assertSame($cartUpdatedAt, $item->cart->fresh()->updated_at?->toISOString());
    }

    public function test_product_reactivation_restores_purchasability(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $this->line($product, $variant, 2);

        $product->update(['is_active' => false]);
        $this->getCart()->assertJsonPath('data.items.0.is_purchasable', false);

        $product->update(['is_active' => true]);
        $this->getCart()->assertJsonPath('data.items.0.is_purchasable', true);
    }

    public function test_made_to_order_and_unpublished_lines_remain_visible_but_not_purchasable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $this->line($product, $variant, 1);

        $product->forceFill(['product_type' => ProductType::MADE_TO_ORDER])->save();
        $this->getCart()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.stock_indicator', 'MADE_TO_ORDER')
            ->assertJsonPath('data.items.0.is_purchasable', false);

        $product->forceFill(['product_type' => ProductType::IN_STOCK, 'is_published' => false])->save();
        $this->getCart()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_inactive_category_line_remains_visible_but_not_purchasable(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $this->line($product, $variant, 1);

        $product->category->update(['is_active' => false]);

        $this->getCart()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_cart_read_persists_no_validation_state(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 3, reserved: 1);
        $item = $this->line($product, $variant, 2);

        $stockBefore = ProductStock::query()->where('product_variant_id', $variant->id)->sole();
        $itemUpdatedAt = $item->fresh()->updated_at?->toISOString();
        $cartUpdatedAt = $item->cart->fresh()->updated_at?->toISOString();
        $stockCount = ProductStock::query()->count();

        $this->getCart()->assertOk();

        $this->assertSame($itemUpdatedAt, $item->fresh()->updated_at?->toISOString());
        $this->assertSame($cartUpdatedAt, $item->cart->fresh()->updated_at?->toISOString());
        $this->assertSame(2, $item->fresh()->quantity);
        $stockAfter = ProductStock::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame($stockBefore->quantity, $stockAfter->quantity);
        $this->assertSame($stockBefore->reserved_quantity, $stockAfter->reserved_quantity);
        $this->assertSame($stockCount, ProductStock::query()->count());
    }

    public function test_admission_and_mutation_share_the_same_error_mapping(): void
    {
        $this->assertSharedMapping(
            fn (Product $product) => $product->forceFill(['product_type' => ProductType::MADE_TO_ORDER])->save(),
            'PRODUCT_NOT_PURCHASABLE',
        );
        $this->assertSharedMapping(
            fn (Product $product) => $product->update(['is_active' => false]),
            'PRODUCT_UNAVAILABLE',
        );
        $this->assertSharedMapping(
            fn (Product $product) => $product->forceFill(['is_published' => false])->save(),
            'PRODUCT_UNAVAILABLE',
        );
        $this->assertSharedMapping(
            fn (Product $product) => $product->category->update(['is_active' => false]),
            'PRODUCT_UNAVAILABLE',
        );
        $this->assertSharedMapping(
            fn (Product $product) => $product->delete(),
            'PRODUCT_UNAVAILABLE',
        );
        $this->assertSharedMapping(
            fn (Product $product, ProductVariant $variant) => $variant->update(['is_active' => false]),
            'INVALID_PRODUCT_VARIANT',
        );
        $this->assertSharedMapping(
            fn (Product $product, ProductVariant $variant) => ProductStock::query()
                ->where('product_variant_id', $variant->id)
                ->update(['quantity' => 0, 'reserved_quantity' => 0]),
            'INSUFFICIENT_STOCK',
        );
    }

    private function assertSharedMapping(callable $stale, string $expected): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 5);
        $stale($product, $variant);

        $this->postJson(self::ITEMS_URL, [
            'product_id' => ProductIdentifier::encode($product),
            'variant_id' => VariantIdentifier::encode($variant),
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonPath('errors.0.code', $expected);

        [$lineProduct, $lineVariant] = $this->stockedProduct(quantity: 5);
        $user = $this->cartCustomer('shared_'.bin2hex(random_bytes(6)));
        $cart = $this->activeCartFor($user);
        $item = $this->itemFor($cart, $lineProduct, $lineVariant, 1);
        $stale($lineProduct, $lineVariant);

        $this->withHeaders($this->authenticateAs($user))
            ->patchJson(self::ITEMS_URL.'/'.CartItemIdentifier::encode($item), ['quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', $expected);
    }

    private function line(Product $product, ProductVariant $variant, int $quantity): CartItem
    {
        $user = $this->cartCustomer('purchasability_'.bin2hex(random_bytes(6)));
        $cart = $this->activeCartFor($user);
        $this->headers = $this->authenticateAs($user);

        return $this->itemFor($cart, $product, $variant, $quantity);
    }

    private function getCart(): TestResponse
    {
        return $this->withHeaders($this->headers)->getJson(self::CART_URL)->assertOk();
    }

    private function setStock(ProductVariant $variant, int $quantity): void
    {
        ProductStock::query()
            ->where('product_variant_id', $variant->id)
            ->update(['quantity' => $quantity, 'reserved_quantity' => 0]);
    }
}
