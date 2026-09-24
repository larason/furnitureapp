<?php

namespace Tests\Integration;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Services\Cart\AddCartItem;
use App\Services\Cart\UpdateCartItemQuantity;
use App\Support\CartStatus;
use App\Support\ProductType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6.3–6.5 concurrency gate on real MariaDB: duplicate/first add merging,
 * max-quantity clamping, and lost-update protection for same-line mutations.
 *
 *   CART_MUTATION_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/CartMutationConcurrencyMysqlTest.php
 */
class CartMutationConcurrencyMysqlTest extends TestCase
{
    private const CONNECTION = 'mysql_cart_mutation';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private const RACES = 10;

    private string $previousDefaultConnection = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');

        $database = (string) getenv('CART_MUTATION_MYSQL_TEST_DATABASE');

        if ($database !== self::DISPOSABLE_DATABASE) {
            $this->markTestSkipped('requires disposable MySQL/MariaDB integration database (set CART_MUTATION_MYSQL_TEST_DATABASE='.self::DISPOSABLE_DATABASE.').');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl and posix extensions required.');
        }

        if (app()->environment('production')) {
            $this->fail('Refusing to run destructive concurrency tests in production.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(
            config('database.connections.mysql'),
            ['database' => $database],
        )]);
        config(['database.default' => self::CONNECTION]);

        DB::purge(self::CONNECTION);
        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        config(['database.default' => $this->previousDefaultConnection]);

        parent::tearDown();
    }

    public function test_repeat_add_race_never_loses_an_increment(): void
    {
        $variant = $this->variant(200);

        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $cart = $this->cart();
            CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => 1]);

            $this->runConcurrently(
                fn () => $this->add($cart->id, $variant->id, 1),
                fn () => $this->add($cart->id, $variant->id, 1),
            );

            $item = CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variant->id)->sole();
            $this->assertSame(3, $item->quantity, "iteration {$iteration}");
            $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
        }
    }

    public function test_first_add_race_creates_one_line_with_combined_quantity(): void
    {
        $variant = $this->variant(200);

        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $cart = $this->cart();

            $this->runConcurrently(
                fn () => $this->add($cart->id, $variant->id, 1),
                fn () => $this->add($cart->id, $variant->id, 1),
            );

            $items = CartItem::query()->where('cart_id', $cart->id)->get();
            $this->assertCount(1, $items, "iteration {$iteration}");
            $this->assertSame(2, $items->first()->quantity, "iteration {$iteration}");
        }
    }

    public function test_concurrent_overflow_is_clamped_to_the_maximum(): void
    {
        $variant = $this->variant(200);

        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $cart = $this->cart();
            CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => 99]);

            $this->runConcurrently(
                fn () => $this->add($cart->id, $variant->id, 1),
                fn () => $this->add($cart->id, $variant->id, 1),
            );

            $this->assertSame(100, CartItem::query()->where('cart_id', $cart->id)->sole()->quantity, "iteration {$iteration}");
        }
    }

    public function test_concurrent_updates_do_not_lose_corrupt_the_line(): void
    {
        $variant = $this->variant(200);

        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $cart = $this->cart();
            $item = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => 1]);

            $this->runConcurrently(
                fn () => $this->update($item->id, 4),
                fn () => $this->update($item->id, 7),
            );

            $final = CartItem::query()->where('cart_id', $cart->id)->sole()->quantity;
            $this->assertContains($final, [4, 7], "iteration {$iteration}");
            $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
        }
    }

    private function add(int $cartId, int $variantId, int $quantity): string
    {
        $cart = Cart::query()->findOrFail($cartId);
        $variant = ProductVariant::query()->findOrFail($variantId);

        app(AddCartItem::class)->add($cart, $variant->product, $variant, $quantity);

        return 'ok';
    }

    private function update(int $itemId, int $quantity): string
    {
        $item = CartItem::query()->findOrFail($itemId);
        app(UpdateCartItemQuantity::class)->update($item, $quantity);

        return 'ok';
    }

    private function variant(int $quantity): ProductVariant
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'product_type' => ProductType::IN_STOCK]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($variant)->create(['warehouse_location' => 'main', 'quantity' => $quantity, 'reserved_quantity' => 0]);

        return $variant;
    }

    private function cart(): Cart
    {
        return Cart::factory()->guestOwned()->create(['status' => CartStatus::ACTIVE]);
    }

    private function runConcurrently(callable ...$workers): void
    {
        $barrier = sys_get_temp_dir().'/cart-mutation-barrier-'.bin2hex(random_bytes(8));
        mkdir($barrier, 0777, true);

        $pids = [];

        foreach ($workers as $index => $worker) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);

            if ($pid === 0) {
                $code = 1;

                try {
                    DB::purge(self::CONNECTION);
                    DB::connection(self::CONNECTION)->selectOne('select 1');

                    touch($barrier.'/ready-'.$index);
                    $this->awaitBarrier($barrier);

                    file_put_contents($barrier.'/result-'.$index, (string) $worker());
                    $code = 0;
                } catch (\Throwable) {
                    $code = 1;
                }

                exit($code);
            }

            $pids[] = $pid;
        }

        $this->awaitReady($barrier, count($workers));
        touch($barrier.'/go');

        $deadline = microtime(true) + 30;

        foreach ($pids as $pid) {
            $status = 0;
            $result = pcntl_waitpid($pid, $status, WNOHANG);

            while ($result === 0 && microtime(true) <= $deadline) {
                usleep(5_000);
                $result = pcntl_waitpid($pid, $status, WNOHANG);
            }

            if ($result === 0) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
                $this->fail('Concurrency worker did not complete in time.');
            }

            $this->assertTrue(
                pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
                'Concurrency worker failed.',
            );
        }

        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($barrier);
        DB::purge(self::CONNECTION);
    }

    private function awaitBarrier(string $barrier): void
    {
        $deadline = microtime(true) + 10;

        while (! is_file($barrier.'/go')) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException('Barrier timeout.');
            }

            usleep(200);
        }
    }

    private function awaitReady(string $barrier, int $count): void
    {
        $deadline = microtime(true) + 10;

        while (count(glob($barrier.'/ready-*') ?: []) < $count) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException('Workers did not become ready in time.');
            }

            usleep(200);
        }
    }
}
