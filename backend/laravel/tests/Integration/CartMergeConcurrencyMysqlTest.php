<?php

namespace Tests\Integration;

use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\MergeGuestCart;
use App\Services\IdempotencyService;
use App\Support\CartStatus;
use App\Support\GuestCartCredential;
use App\Support\ProductType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 6.8 CART-005 concurrency gate on real MariaDB: same-key exactly-once,
 * different-key same-source single effect, and overlapping target-line
 * consolidation without uniqueness violations.
 *
 *   CART_MERGE_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/CartMergeConcurrencyMysqlTest.php
 */
class CartMergeConcurrencyMysqlTest extends TestCase
{
    private const CONNECTION = 'mysql_cart_merge';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private string $previousDefaultConnection = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');

        $database = (string) getenv('CART_MERGE_MYSQL_TEST_DATABASE');

        if ($database !== self::DISPOSABLE_DATABASE) {
            $this->markTestSkipped('requires disposable MySQL/MariaDB integration database (set CART_MERGE_MYSQL_TEST_DATABASE='.self::DISPOSABLE_DATABASE.').');
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

    public function test_concurrent_same_key_merge_produces_exactly_one_effect(): void
    {
        [$user, $guest, $raw] = $this->userAndGuest(quantity: 3);
        $digest = (string) $guest->guest_token_digest;
        $key = (string) Str::uuid();

        $results = $this->runConcurrently(
            fn (): string => $this->idempotentMerge($user->id, $digest, $key),
            fn (): string => $this->idempotentMerge($user->id, $digest, $key),
        );

        // Both same-key requests must receive the merge result: one performs it,
        // the other replays the recorded outcome (Phase 6.8 §59/§98).
        $this->assertSame('merged', $results[0]);
        $this->assertSame('merged', $results[1]);

        $target = Cart::query()->where('user_id', $user->id)->where('status', CartStatus::ACTIVE)->sole();
        $line = CartItem::query()->where('cart_id', $target->id)->sole();

        $this->assertSame(3, $line->quantity);
        $this->assertSame(CartStatus::INACTIVE, $guest->fresh()->status);
        $this->assertSame(1, IdempotencyKey::query()->where('action', MergeGuestCart::ACTION)->count());
    }

    public function test_concurrent_different_key_merge_of_the_same_source_is_not_duplicated(): void
    {
        [$user, $guest, $raw] = $this->userAndGuest(quantity: 4);
        $digest = (string) $guest->guest_token_digest;

        $results = $this->runConcurrently(
            fn (): string => $this->idempotentMerge($user->id, $digest, (string) Str::uuid()),
            fn (): string => $this->idempotentMerge($user->id, $digest, (string) Str::uuid()),
        );

        // Exactly one merge succeeds; the other observes the retired/unavailable
        // source (canonical guest-credential outcome), never a duplicate merge.
        sort($results);
        $this->assertSame(['error:AUTHENTICATION_REQUIRED', 'merged'], $results);

        $target = Cart::query()->where('user_id', $user->id)->where('status', CartStatus::ACTIVE)->sole();
        $line = CartItem::query()->where('cart_id', $target->id)->sole();

        $this->assertSame(4, $line->quantity);
        $this->assertSame(CartStatus::INACTIVE, $guest->fresh()->status);
        $this->assertSame(1, CartItem::query()->where('cart_id', $target->id)->count());
    }

    public function test_concurrent_overlapping_merges_keep_the_target_line_unique(): void
    {
        [$product, $variant] = $this->stockedProduct();
        $user = $this->user();
        [$first] = $this->guestFor($product, $variant, 3);
        [$second] = $this->guestFor($product, $variant, 4);

        $results = $this->runConcurrently(
            fn (): string => $this->plainMerge($user->id, (string) $first->guest_token_digest),
            fn (): string => $this->plainMerge($user->id, (string) $second->guest_token_digest),
        );

        $this->assertSame(['merged', 'merged'], $results);

        $target = Cart::query()->where('user_id', $user->id)->where('status', CartStatus::ACTIVE)->sole();
        $line = CartItem::query()->where('cart_id', $target->id)->sole();

        $this->assertSame(7, $line->quantity);
        $this->assertSame(1, CartItem::query()->where('cart_id', $target->id)->count());
    }

    private function idempotentMerge(int $userId, string $digest, string $key): string
    {
        $actor = User::query()->findOrFail($userId);

        try {
            app(IdempotencyService::class)->execute(
                $actor,
                MergeGuestCart::ACTION,
                $key,
                ['source_guest_digest' => $digest],
                function () use ($actor, $digest): array {
                    app(MergeGuestCart::class)->merge($actor, $digest);

                    return ['merged' => true];
                },
            );
        } catch (ApiException $exception) {
            return 'error:'.$exception->errorCode()->value;
        }

        return 'merged';
    }

    private function plainMerge(int $userId, string $digest): string
    {
        app(MergeGuestCart::class)->merge(User::query()->findOrFail($userId), $digest);

        return 'merged';
    }

    /** @return array{0: User, 1: Cart, 2: string} */
    private function userAndGuest(int $quantity): array
    {
        [$product, $variant] = $this->stockedProduct();

        return [$this->user(), ...$this->guestFor($product, $variant, $quantity)];
    }

    private function user(): User
    {
        return User::factory()->customer()->create(['clerk_user_id' => 'merge_'.bin2hex(random_bytes(6))]);
    }

    /** @return array{0: Cart, 1: string} */
    private function guestFor(Product $product, ProductVariant $variant, int $quantity): array
    {
        $raw = GuestCartCredential::generate();
        $cart = Cart::query()->create([
            'user_id' => null,
            'guest_token_digest' => GuestCartCredential::digest($raw),
            'status' => CartStatus::ACTIVE,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);

        return [$cart, $raw];
    }

    /** @return array{0: Product, 1: ProductVariant} */
    private function stockedProduct(): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'product_type' => ProductType::IN_STOCK]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductStock::factory()->forVariant($variant)->create(['warehouse_location' => 'main', 'quantity' => 100, 'reserved_quantity' => 0]);

        return [$product, $variant];
    }

    /** @return list<string> */
    private function runConcurrently(callable ...$workers): array
    {
        $barrier = sys_get_temp_dir().'/cart-merge-barrier-'.bin2hex(random_bytes(8));
        mkdir($barrier, 0777, true);

        $pids = [];

        try {
            foreach ($workers as $index => $worker) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);

                if ($pid === 0) {
                    exit($this->runWorker($barrier, $index, $worker));
                }

                $pids[] = $pid;
            }

            $this->awaitReady($barrier, count($workers));
            touch($barrier.'/go');

            $this->reapWorkers($pids, $barrier);

            $results = [];
            foreach (array_keys($workers) as $index) {
                $results[] = $this->resultFor($barrier, $index);
            }

            return $results;
        } finally {
            $this->terminateWorkers($pids);
            $this->destroyBarrier($barrier);
            DB::purge(self::CONNECTION);
        }
    }

    private function runWorker(string $barrier, int $index, callable $worker): int
    {
        try {
            DB::purge(self::CONNECTION);
            DB::connection(self::CONNECTION)->selectOne('select 1');

            touch($barrier.'/ready-'.$index);
            $this->awaitBarrier($barrier);

            file_put_contents($barrier.'/result-'.$index, (string) $worker());

            return 0;
        } catch (\Throwable $exception) {
            @file_put_contents($barrier.'/result-'.$index, 'crash:'.$exception::class.':'.$exception->getMessage());

            return 1;
        }
    }

    /** @param list<int> $pids */
    private function reapWorkers(array $pids, string $barrier): void
    {
        $deadline = microtime(true) + 30;

        foreach ($pids as $index => $pid) {
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
                'Concurrency worker failed: '.$this->resultFor($barrier, $index),
            );
        }
    }

    private function resultFor(string $barrier, int $index): string
    {
        $file = $barrier.'/result-'.$index;

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    /** @param list<int> $pids */
    private function terminateWorkers(array $pids): void
    {
        foreach ($pids as $pid) {
            $status = 0;

            if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
        }
    }

    private function destroyBarrier(string $barrier): void
    {
        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($barrier);
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
