<?php

namespace Tests\Integration;

use App\Models\Cart;
use App\Models\User;
use App\Services\Cart\CartHolder;
use App\Services\Cart\GetOrCreateActiveCart;
use App\Support\CartStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6.2 creation-race gate: proves the authenticated lazy-create path
 * cannot create two ACTIVE carts under real concurrent inserts, which relies
 * on the generated `active_user_guard` unique constraint.
 *
 *   CART_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/CartConcurrencyMysqlTest.php
 */
class CartConcurrencyMysqlTest extends TestCase
{
    private const CONNECTION = 'mysql_cart';

    private const DISPOSABLE_DATABASE = 'furnitureapp_test_disposable';

    private const RACES = 10;

    private string $previousDefaultConnection = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = (string) config('database.default');

        $database = (string) getenv('CART_MYSQL_TEST_DATABASE');

        if ($database !== self::DISPOSABLE_DATABASE) {
            $this->markTestSkipped('requires disposable MySQL/MariaDB integration database (set CART_MYSQL_TEST_DATABASE='.self::DISPOSABLE_DATABASE.').');
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

    public function test_concurrent_first_get_creates_exactly_one_active_cart(): void
    {
        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $user = User::factory()->staff()->create(['clerk_user_id' => 'staff_cart_race_'.$iteration]);

            $results = $this->runConcurrently(
                fn (): string => $this->resolveCartId($user->id),
                fn (): string => $this->resolveCartId($user->id),
            );

            $this->assertCount(2, $results);
            $this->assertNotContains(null, $results, "iteration {$iteration}");
            $this->assertCount(1, array_unique($results), "iteration {$iteration}");
            $this->assertSame(1, Cart::query()->where('user_id', $user->id)->where('status', CartStatus::ACTIVE)->count(), "iteration {$iteration}");
        }
    }

    /** @return array<int, string|null> */
    private function runConcurrently(callable ...$workers): array
    {
        $barrier = sys_get_temp_dir().'/cart-barrier-'.bin2hex(random_bytes(8));
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

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        foreach (array_keys($workers) as $index) {
            $file = $barrier.'/result-'.$index;
            $results[] = is_file($file) ? file_get_contents($file) : null;
        }

        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($barrier);
        DB::purge(self::CONNECTION);

        return $results;
    }

    private function resolveCartId(int $userId): string
    {
        $user = User::query()->findOrFail($userId);
        $cart = app(GetOrCreateActiveCart::class)->forHolder(CartHolder::customer($user));

        return (string) $cart->getKey();
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
                return;
            }

            usleep(200);
        }
    }
}
