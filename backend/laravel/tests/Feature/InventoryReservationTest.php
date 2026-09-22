<?php

namespace Tests\Feature;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemInventoryAllocation;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryAllocator;
use App\Services\InventoryAdjustmentService;
use App\Support\ApiErrorCode;
use App\Support\ConcurrentTransaction;
use App\Support\InventoryAdjustmentReason;
use App\Support\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_holds_units_without_changing_physical_quantity(): void
    {
        $variant = ProductVariant::factory()->create();
        $stock = $this->stock($variant, 'main', 10);
        [$order, $item] = $this->orderWithItem($variant, 4);

        $this->allocator()->reserve($order);

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(4, $stock->fresh()->reserved_quantity);
        $this->assertSame(6, $stock->fresh()->available_quantity);
        $this->assertAllocationQuantity($item, $stock, 4);
    }

    public function test_reserve_spans_multiple_locations_deterministically(): void
    {
        $variant = ProductVariant::factory()->create();
        $dar = $this->stock($variant, 'dar-es-salaam', 3);
        $main = $this->stock($variant, 'main', 2);
        [$order, $item] = $this->orderWithItem($variant, 4);

        $this->allocator()->reserve($order);

        $this->assertAllocationQuantity($item, $dar, 3);
        $this->assertAllocationQuantity($item, $main, 1);
        $this->assertSame(3, $dar->fresh()->quantity);
        $this->assertSame(2, $main->fresh()->quantity);
        $this->assertSame(3, $dar->fresh()->reserved_quantity);
        $this->assertSame(1, $main->fresh()->reserved_quantity);
    }

    public function test_reserve_rolls_back_without_partial_allocation_on_insufficient_stock(): void
    {
        $variant = ProductVariant::factory()->create();
        $stock = $this->stock($variant, 'main', 2);
        [$order, $item] = $this->orderWithItem($variant, 5);

        $this->expectApiError(ApiErrorCode::INSUFFICIENT_STOCK, function () use ($order): void {
            DB::transaction(fn () => $this->allocator()->reserve($order));
        });

        $this->assertSame(0, $stock->fresh()->reserved_quantity);
        $this->assertSame(0, OrderItemInventoryAllocation::query()->where('order_item_id', $item->id)->count());
    }

    public function test_multi_item_reservation_is_all_or_nothing(): void
    {
        $rich = ProductVariant::factory()->create();
        $poor = ProductVariant::factory()->create();
        $richStock = $this->stock($rich, 'main', 5);
        $poorStock = $this->stock($poor, 'main', 1);

        $order = Order::factory()->create();
        $richItem = $this->item($order, $rich, 2);
        $poorItem = $this->item($order, $poor, 2);

        $this->expectApiError(ApiErrorCode::INSUFFICIENT_STOCK, function () use ($order): void {
            DB::transaction(fn () => $this->allocator()->reserve($order));
        });

        $this->assertSame(0, $richStock->fresh()->reserved_quantity);
        $this->assertSame(0, $poorStock->fresh()->reserved_quantity);
        $this->assertSame(0, OrderItemInventoryAllocation::query()->where('order_item_id', $richItem->id)->count());
        $this->assertSame(0, OrderItemInventoryAllocation::query()->where('order_item_id', $poorItem->id)->count());
    }

    public function test_repeated_variant_items_share_one_locked_allocation(): void
    {
        $variant = ProductVariant::factory()->create();
        $stock = $this->stock($variant, 'main', 3);

        $order = Order::factory()->create();
        $first = $this->item($order, $variant, 2);
        $second = $this->item($order, $variant, 1);

        $this->allocator()->reserve($order);

        $this->assertSame(3, $stock->fresh()->reserved_quantity);
        $this->assertAllocationQuantity($first, $stock, 2);
        $this->assertAllocationQuantity($second, $stock, 1);
    }

    public function test_reserve_rejects_a_second_reservation_for_the_same_order(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->stock($variant, 'main', 10);
        [$order] = $this->orderWithItem($variant, 1);

        $this->allocator()->reserve($order);

        $this->expectApiError(ApiErrorCode::RESOURCE_VERSION_CONFLICT, fn () => $this->allocator()->reserve($order));
    }

    public function test_release_restores_reserved_only_and_is_idempotent(): void
    {
        $variant = ProductVariant::factory()->create();
        $stock = $this->stock($variant, 'main', 10);
        [$order] = $this->orderWithItem($variant, 4);

        $this->allocator()->reserve($order);
        $this->allocator()->release($order);

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(0, $stock->fresh()->reserved_quantity);
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());

        $this->allocator()->release($order);

        $this->assertSame(0, $stock->fresh()->reserved_quantity);
    }

    public function test_consume_converts_reserved_into_sold_and_is_idempotent(): void
    {
        $variant = ProductVariant::factory()->create();
        $stock = $this->stock($variant, 'main', 10);
        [$order] = $this->orderWithItem($variant, 3);

        $this->allocator()->reserve($order);
        $this->assertSame(7, $stock->fresh()->available_quantity);

        $this->allocator()->consume($order);

        $this->assertSame(7, $stock->fresh()->quantity);
        $this->assertSame(0, $stock->fresh()->reserved_quantity);
        $this->assertSame(7, $stock->fresh()->available_quantity);
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());

        $this->allocator()->consume($order);

        $this->assertSame(7, $stock->fresh()->quantity);
        $this->assertSame(0, $stock->fresh()->reserved_quantity);
    }

    public function test_reservation_reduces_public_catalog_availability(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'reserved-sofa',
            'product_type' => ProductType::IN_STOCK,
            'is_active' => true,
            'is_published' => true,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        $this->stock($variant, 'main', 1);
        [$order] = $this->orderWithItem($variant, 1);

        $this->allocator()->reserve($order);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.availability', 'unavailable');

        $this->allocator()->release($order);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.availability', 'available');
    }

    public function test_adjustment_cannot_reduce_quantity_below_active_reservation(): void
    {
        $variant = ProductVariant::factory()->create();
        $stock = $this->stock($variant, 'main', 10);
        [$order] = $this->orderWithItem($variant, 8);

        $this->allocator()->reserve($order);

        $actor = User::factory()->staff()->create(['clerk_user_id' => 'staff_reservation']);
        $service = app(InventoryAdjustmentService::class);

        $this->expectApiError(
            ApiErrorCode::INVALID_VALUE,
            function () use ($service, $stock, $actor): void {
                DB::transaction(fn () => $service->adjust($stock, -5, InventoryAdjustmentReason::CORRECTION, $actor, (string) Str::uuid(), null));
            },
        );

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(8, $stock->fresh()->reserved_quantity);
    }

    public function test_concurrent_transaction_returns_the_callback_result_at_top_level(): void
    {
        $this->assertSame('ok', ConcurrentTransaction::run(fn (): string => 'ok'));
    }

    public function test_nested_concurrent_transaction_participates_in_the_existing_transaction(): void
    {
        DB::beginTransaction();
        $level = DB::transactionLevel();

        try {
            $innerLevel = ConcurrentTransaction::run(fn (): int => DB::transactionLevel());

            $this->assertSame($level, $innerLevel);
            $this->assertSame($level, DB::transactionLevel());
        } finally {
            DB::rollBack();
        }
    }

    private function allocator(): InventoryAllocator
    {
        return app(InventoryAllocator::class);
    }

    private function stock(ProductVariant $variant, string $location, int $quantity): ProductStock
    {
        return ProductStock::factory()->forVariant($variant)->create([
            'warehouse_location' => $location,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);
    }

    /** @return array{0: Order, 1: OrderItem} */
    private function orderWithItem(ProductVariant $variant, int $quantity): array
    {
        $order = Order::factory()->create();

        return [$order, $this->item($order, $variant, $quantity)];
    }

    private function item(Order $order, ProductVariant $variant, int $quantity): OrderItem
    {
        $unitPrice = (int) $variant->price_amount;

        return OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name' => 'Test item',
            'variant_name' => $variant->variant_name,
            'unit_price_amount' => $unitPrice,
            'quantity' => $quantity,
            'line_total_amount' => $unitPrice * $quantity,
        ]);
    }

    private function assertAllocationQuantity(OrderItem $item, ProductStock $stock, int $quantity): void
    {
        $this->assertDatabaseHas('order_item_inventory_allocations', [
            'order_item_id' => $item->id,
            'product_stock_id' => $stock->id,
            'quantity' => $quantity,
        ]);
    }

    private function expectApiError(ApiErrorCode $code, callable $callback): void
    {
        try {
            $callback();
        } catch (ApiException $exception) {
            $this->assertSame($code, $exception->errorCode());

            return;
        }

        $this->fail('Expected API error '.$code->value.' was not raised.');
    }
}
