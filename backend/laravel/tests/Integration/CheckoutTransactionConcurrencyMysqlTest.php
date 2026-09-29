<?php

namespace Tests\Integration;

use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemInventoryAllocation;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Checkout\CheckoutCommand;
use App\Services\Checkout\CheckoutTransaction;
use App\Support\CartStatus;
use App\Support\ProductType;
use Tests\Support\RunsConcurrentWorkers;
use Tests\Support\UsesDisposableMysqlDatabase;
use Tests\TestCase;

/**
 * Phase 7.7 Checkout transaction concurrency proof on real MariaDB: same-key
 * exactly-once, different-key same-Cart single Order, and last-unit oversell
 * prevention. SQLite proves atomicity/rollback; only MariaDB proves row locks.
 *
 *   CHECKOUT_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/CheckoutTransactionConcurrencyMysqlTest.php
 */
class CheckoutTransactionConcurrencyMysqlTest extends TestCase
{
    use RunsConcurrentWorkers;
    use UsesDisposableMysqlDatabase;

    private const RACES = 5;

    protected function databaseConnectionName(): string
    {
        return 'mysql_checkout';
    }

    protected function databaseEnvironmentVariable(): string
    {
        return 'CHECKOUT_MYSQL_TEST_DATABASE';
    }

    public function test_concurrent_same_key_checkout_has_one_business_effect(): void
    {
        [$product, $variant] = $this->stockedVariant(quantity: 10);
        $customer = $this->customer('same_key');
        $this->cartWithItem($customer, $product, $variant, 2);

        $results = $this->runConcurrentWorkers(
            fn (): string => $this->checkout($customer->id, 'shared-key'),
            fn (): string => $this->checkout($customer->id, 'shared-key'),
        );

        $this->assertStringStartsWith('ok:', $results[0]);
        $this->assertStringStartsWith('ok:', $results[1]);
        $this->assertSame($results[0], $results[1]);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, OrderItem::query()->count());
        $this->assertSame(1, OrderItemInventoryAllocation::query()->count());
        $this->assertSame(1, OrderStatusHistory::query()->count());
        $this->assertSame(2, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_concurrent_different_key_same_cart_creates_one_order(): void
    {
        [$product, $variant] = $this->stockedVariant(quantity: 10);
        $customer = $this->customer('different_key');
        $this->cartWithItem($customer, $product, $variant, 2);

        $results = $this->runConcurrentWorkers(
            fn (): string => $this->checkout($customer->id, 'key-a'),
            fn (): string => $this->checkout($customer->id, 'key-b'),
        );

        sort($results);
        $this->assertCount(1, array_filter($results, fn (string $r): bool => str_starts_with($r, 'ok:')));
        $this->assertContains('error:CART_INVALID', $results);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_last_unit_checkout_reserves_once_without_oversell(): void
    {
        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            [$product, $variant, $stock] = $this->stockedVariant(quantity: 1);
            $first = $this->customer("last_unit_a_{$iteration}");
            $second = $this->customer("last_unit_b_{$iteration}");
            $this->cartWithItem($first, $product, $variant, 1);
            $this->cartWithItem($second, $product, $variant, 1);

            $results = $this->runConcurrentWorkers(
                fn (): string => $this->checkout($first->id, "unit-a-{$iteration}"),
                fn (): string => $this->checkout($second->id, "unit-b-{$iteration}"),
            );

            sort($results);
            $this->assertCount(1, array_filter($results, fn (string $r): bool => str_starts_with($r, 'ok:')), "iteration {$iteration}");
            $this->assertContains('error:INSUFFICIENT_STOCK', $results, "iteration {$iteration}");
            $this->assertSame(1, $stock->fresh()->reserved_quantity, "iteration {$iteration}");
            $this->assertSame(1, $stock->fresh()->quantity, "iteration {$iteration}");
            $this->assertSame(0, $stock->fresh()->available_quantity, "iteration {$iteration}");

            $orders = Order::query()->whereIn('customer_id', [$first->id, $second->id])->count();
            $this->assertSame(1, $orders, "iteration {$iteration}");
        }
    }

    private function checkout(int $customerId, string $key): string
    {
        $customer = User::query()->findOrFail($customerId);

        try {
            $outcome = app(CheckoutTransaction::class)->execute(CheckoutCommand::pickup($customer, $key));
        } catch (ApiException $exception) {
            return 'error:'.$exception->errorCode()->value;
        }

        return 'ok:'.$outcome->body['order_reference'];
    }

    private function customer(string $suffix): User
    {
        return User::factory()->customer()->create(['clerk_user_id' => 'checkout_'.$suffix]);
    }

    /** @return array{0: Product, 1: ProductVariant, 2: ProductStock} */
    private function stockedVariant(int $quantity): array
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
            'is_active' => true,
            'price_amount' => 25_000,
            'price_currency' => 'TZS',
        ]);
        $stock = ProductStock::factory()->forVariant($variant)->create([
            'warehouse_location' => 'main',
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);

        return [$product, $variant, $stock];
    }

    private function cartWithItem(User $customer, Product $product, ProductVariant $variant, int $quantity): Cart
    {
        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'guest_token_digest' => null,
            'status' => CartStatus::ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);

        return $cart;
    }
}
