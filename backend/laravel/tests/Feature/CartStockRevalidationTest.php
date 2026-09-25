<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CartStatus;
use App\Support\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

/**
 * Phase 6.7 Cart-wide live stock revalidation: a batched, read-only projection
 * that reflects current Group E inventory without touching the Cart.
 */
class CartStockRevalidationTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    private const CART_URL = '/api/v1/me/cart';

    public function test_empty_cart_revalidation_performs_no_inventory_queries(): void
    {
        [$user, $cart] = $this->customerWithCart();

        $stockQueries = $this->countProductStockQueries(function () use ($user): void {
            $this->getCart($user)->assertJsonPath('data.items', [])->assertJsonPath('data.items_count', 0);
        });

        $this->assertSame(0, $stockQueries);
        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_each_line_reports_its_own_current_purchasability(): void
    {
        [$user] = $this->customerWithCart();
        [$enoughProduct, $enoughVariant] = $this->stockedProduct(quantity: 4);
        [$shortProduct, $shortVariant] = $this->stockedProduct(quantity: 3, price: 6000);
        [$inactiveProduct, $inactiveVariant] = $this->stockedProduct(quantity: 10);
        $inactiveProduct->update(['is_active' => false]);

        $this->addLine($user, $enoughProduct, $enoughVariant, 2);
        $this->addLine($user, $shortProduct, $shortVariant, 5);
        $this->addLine($user, $inactiveProduct, $inactiveVariant, 1);

        $response = $this->getCart($user)->assertOk()->assertJsonPath('data.items_count', 3);
        $byQuantity = collect($response->json('data.items'))->keyBy('quantity');

        $this->assertTrue($byQuantity[2]['is_purchasable']);
        $this->assertFalse($byQuantity[5]['is_purchasable']);
        $this->assertFalse($byQuantity[1]['is_purchasable']);
    }

    public function test_stock_drop_between_reads_is_reflected_without_cart_mutation(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 5);
        $item = $this->addLine($user, $product, $variant, 5);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', true);

        $itemUpdatedAt = $item->fresh()->updated_at?->toISOString();
        $this->setStock($variant, 4);

        $this->getCart($user)
            ->assertJsonPath('data.items.0.availability', 'available')
            ->assertJsonPath('data.items.0.is_purchasable', false);

        $this->assertSame(5, $item->fresh()->quantity);
        $this->assertSame($itemUpdatedAt, $item->fresh()->updated_at?->toISOString());
    }

    public function test_stock_recovery_restores_purchasability(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 2);
        $this->addLine($user, $product, $variant, 5);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', false);

        $this->setStock($variant, 5);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', true);
    }

    public function test_reservation_created_elsewhere_reduces_purchasability(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 5);
        $this->addLine($user, $product, $variant, 4);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', true);

        $this->reserve($variant, 2);

        $this->getCart($user)
            ->assertJsonPath('data.items.0.availability', 'available')
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_reservation_release_restores_purchasability(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 5, reserved: 2);
        $this->addLine($user, $product, $variant, 4);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', false);

        $this->reserve($variant, 0);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', true);
    }

    public function test_multi_location_availability_aggregates_per_variant(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 3, reserved: 1, location: 'main');
        ProductStock::factory()->forVariant($variant)->create(['warehouse_location' => 'second', 'quantity' => 4, 'reserved_quantity' => 1]);
        $this->addLine($user, $product, $variant, 5);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', true);

        ProductStock::query()->where('warehouse_location', 'second')->update(['quantity' => 2, 'reserved_quantity' => 0]);

        $this->getCart($user)->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_sibling_variant_stock_is_never_borrowed(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $unavailable] = $this->stockedProduct(quantity: 0);
        $sibling = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($sibling)->create(['warehouse_location' => 'main', 'quantity' => 10, 'reserved_quantity' => 0]);

        $this->addLine($user, $product, $unavailable, 1);

        $this->getCart($user)
            ->assertJsonPath('data.items.0.availability', 'unavailable')
            ->assertJsonPath('data.items.0.is_purchasable', false);
    }

    public function test_invalid_lines_short_circuit_to_not_purchasable(): void
    {
        [$user] = $this->customerWithCart();

        [$inactiveProduct, $inactiveVariant] = $this->stockedProduct(quantity: 10);
        $inactiveProduct->update(['is_active' => false]);
        $this->addLine($user, $inactiveProduct, $inactiveVariant, 1);

        [$madeToOrderProduct, $madeToOrderVariant] = $this->stockedProduct(quantity: 10);
        $madeToOrderProduct->forceFill(['product_type' => ProductType::MADE_TO_ORDER])->save();
        $this->addLine($user, $madeToOrderProduct, $madeToOrderVariant, 1);

        [$activeProduct, $variantInactive] = $this->stockedProduct(quantity: 10);
        $variantInactive->update(['is_active' => false]);
        $this->addLine($user, $activeProduct, $variantInactive, 1);

        $this->getCart($user)
            ->assertJsonPath('data.items_count', 3)
            ->assertJsonPath('data.items.0.is_purchasable', false)
            ->assertJsonPath('data.items.1.is_purchasable', false)
            ->assertJsonPath('data.items.2.is_purchasable', false);
    }

    public function test_low_stock_within_quantity_is_purchasable_and_insufficient_is_not(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 4);
        $this->addLine($user, $product, $variant, 2);

        $this->getCart($user)
            ->assertJsonPath('data.items.0.stock_indicator', 'LOW_STOCK')
            ->assertJsonPath('data.items.0.is_purchasable', true);

        [$product2, $variant2] = $this->stockedProduct(quantity: 5);
        $this->addLine($user, $product2, $variant2, 5);
        $this->setStock($variant2, 4);

        $response = $this->getCart($user)->assertOk()->assertJsonPath('data.items_count', 2);
        $byQuantity = collect($response->json('data.items'))->keyBy('quantity');

        $this->assertSame('LOW_STOCK', $byQuantity[5]['stock_indicator']);
        $this->assertSame('available', $byQuantity[5]['availability']);
        $this->assertFalse($byQuantity[5]['is_purchasable']);
    }

    public function test_revalidation_persists_nothing_and_reserves_nothing(): void
    {
        [$user, $cart] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 3, reserved: 1);
        $item = $this->addLine($user, $product, $variant, 2);

        $stock = ProductStock::query()->where('product_variant_id', $variant->id)->sole();
        $cartUpdatedAt = $cart->fresh()->updated_at?->toISOString();
        $itemUpdatedAt = $item->fresh()->updated_at?->toISOString();

        $this->getCart($user)->assertOk();

        $this->assertSame($cartUpdatedAt, $cart->fresh()->updated_at?->toISOString());
        $this->assertSame($itemUpdatedAt, $item->fresh()->updated_at?->toISOString());
        $this->assertSame($stock->quantity, $stock->fresh()->quantity);
        $this->assertSame($stock->reserved_quantity, $stock->fresh()->reserved_quantity);
    }

    public function test_stock_query_growth_is_bounded_by_cart_line_count(): void
    {
        [$smallUser] = $this->customerWithCart();
        [$smallProduct, $smallVariant] = $this->stockedProduct(quantity: 10);
        $this->addLine($smallUser, $smallProduct, $smallVariant, 1);

        $largeUser = $this->cartCustomer('revalidation_large');
        $this->activeCartFor($largeUser);
        for ($i = 0; $i < 10; $i++) {
            [$product, $variant] = $this->stockedProduct(quantity: 10);
            $this->addLine($largeUser, $product, $variant, 1);
        }

        $smallQueries = $this->countProductStockQueries(fn () => $this->getCart($smallUser)->assertJsonPath('data.items_count', 1));
        $largeQueries = $this->countProductStockQueries(fn () => $this->getCart($largeUser)->assertJsonPath('data.items_count', 10));

        $this->assertSame($smallQueries, $largeQueries);
    }

    public function test_product_relation_is_not_lazy_loaded_per_cart_line(): void
    {
        [$smallUser] = $this->customerWithCart();
        [$smallProduct, $smallVariant] = $this->stockedProduct(quantity: 10);
        $this->addLine($smallUser, $smallProduct, $smallVariant, 1);

        $largeUser = $this->cartCustomer('revalidation_product_large');
        $this->activeCartFor($largeUser);
        for ($i = 0; $i < 10; $i++) {
            [$product, $variant] = $this->stockedProduct(quantity: 10);
            $this->addLine($largeUser, $product, $variant, 1);
        }

        $smallProducts = $this->queriesMatching(fn () => $this->getCart($smallUser), 'from "products"');
        $largeProducts = $this->queriesMatching(fn () => $this->getCart($largeUser), 'from "products"');

        $this->assertSame(count($smallProducts), count($largeProducts));
        $this->assertSame(1, count($largeProducts));
    }

    public function test_batched_stock_load_targets_only_eligible_line_variants(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $eligible] = $this->stockedProduct(quantity: 10);
        $sibling = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($sibling)->create(['warehouse_location' => 'main', 'quantity' => 10, 'reserved_quantity' => 0]);
        $this->addLine($user, $product, $eligible, 1);

        $stockQueries = $this->queriesMatching(fn () => $this->getCart($user), 'product_stocks');
        $bindings = array_map('intval', array_merge(...array_map(fn (array $entry): array => $entry['bindings'], $stockQueries)));

        $this->assertCount(1, $stockQueries);
        $this->assertContains((int) $eligible->getKey(), $bindings);
        $this->assertNotContains((int) $sibling->getKey(), $bindings);
    }

    public function test_made_to_order_line_loads_no_stock(): void
    {
        [$user] = $this->customerWithCart();
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $product->forceFill(['product_type' => ProductType::MADE_TO_ORDER])->save();
        $this->addLine($user, $product, $variant, 1);

        $stockQueries = $this->queriesMatching(fn () => $this->getCart($user), 'product_stocks');

        $this->assertCount(0, $stockQueries);
    }

    /** @return array{0: User, 1: Cart} */
    private function customerWithCart(): array
    {
        $user = $this->cartCustomer();
        $cart = $this->activeCartFor($user);

        return [$user, $cart];
    }

    private function addLine(User $user, Product $product, ProductVariant $variant, int $quantity): CartItem
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::ACTIVE)
            ->sole();

        return $this->itemFor($cart, $product, $variant, $quantity);
    }

    private function getCart(User $user): TestResponse
    {
        return $this->withHeaders($this->authenticateAs($user))->getJson(self::CART_URL)->assertOk();
    }

    private function setStock(ProductVariant $variant, int $quantity): void
    {
        ProductStock::query()->where('product_variant_id', $variant->id)->update(['quantity' => $quantity, 'reserved_quantity' => 0]);
    }

    private function reserve(ProductVariant $variant, int $reserved): void
    {
        ProductStock::query()->where('product_variant_id', $variant->id)->update(['reserved_quantity' => $reserved]);
    }

    private function countProductStockQueries(callable $callback): int
    {
        return count($this->queriesMatching($callback, 'product_stocks'));
    }

    /**
     * @return list<array{query: string, bindings: array<int, mixed>}>
     */
    private function queriesMatching(callable $callback, string $needle): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();
        } finally {
            $log = DB::getQueryLog();
            DB::disableQueryLog();
        }

        return array_values(array_filter($log, fn (array $entry): bool => str_contains($entry['query'], $needle)));
    }
}
