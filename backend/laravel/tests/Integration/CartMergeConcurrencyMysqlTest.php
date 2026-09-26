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
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentWorkers;
use Tests\Support\UsesDisposableMysqlDatabase;
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
    use RunsConcurrentWorkers;
    use UsesDisposableMysqlDatabase;

    protected function databaseConnectionName(): string
    {
        return 'mysql_cart_merge';
    }

    protected function databaseEnvironmentVariable(): string
    {
        return 'CART_MERGE_MYSQL_TEST_DATABASE';
    }

    public function test_concurrent_same_key_merge_produces_exactly_one_effect(): void
    {
        [$user, $guest] = $this->userAndGuest(quantity: 3);
        $digest = (string) $guest->guest_token_digest;
        $key = (string) Str::uuid();

        $results = $this->runConcurrentWorkers(
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
        [$user, $guest] = $this->userAndGuest(quantity: 4);
        $digest = (string) $guest->guest_token_digest;

        $results = $this->runConcurrentWorkers(
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

        $results = $this->runConcurrentWorkers(
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
}
