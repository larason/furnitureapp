<?php

namespace App\Services\Inventory;

use App\Exceptions\Api\ApiException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemInventoryAllocation;
use App\Models\ProductStock;
use App\Support\ApiErrorCode;
use App\Support\ConcurrentTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Authoritative inventory reservation primitive (Phase 5.10).
 *
 * All operations use short transactions with deterministic pessimistic row
 * locks: Order row first, then ProductStock rows ordered by id. Multi-location
 * allocations are persisted per OrderItem so release/consume target the exact
 * rows that were reserved.
 */
final class InventoryAllocator
{
    /**
     * Reserve stock for every item of an order, across locations, all-or-nothing.
     *
     * Callers (checkout/order workflows) own order-state and eligibility
     * validation; this primitive owns stock state only and expects an order that
     * is valid, not already reserved, with immutable item quantities created in
     * the same business transaction.
     */
    public function reserve(Order $order): void
    {
        ConcurrentTransaction::run(function () use ($order): void {
            $this->lockOrder($order->getKey());

            $items = $this->orderItems($order);

            if ($items->isEmpty()) {
                return;
            }

            $this->assertNotAlreadyReserved($items);

            $stocks = $this->lockCandidateStocks($items);

            foreach ($items as $item) {
                $this->reserveItem($item, $stocks);
            }
        });
    }

    public function release(Order $order): void
    {
        $this->mutateReservation($order, false);
    }

    public function consume(Order $order): void
    {
        $this->mutateReservation($order, true);
    }

    private function mutateReservation(Order $order, bool $consume): void
    {
        ConcurrentTransaction::run(function () use ($order, $consume): void {
            $this->lockOrder($order->getKey());

            $allocations = $this->allocationsFor($order);

            if ($allocations->isEmpty()) {
                return;
            }

            $stocks = $this->lockAllocationStocks($allocations);

            foreach ($allocations as $allocation) {
                $this->applyAllocation($stocks->get($allocation->product_stock_id), $allocation, $consume);
            }

            OrderItemInventoryAllocation::query()->whereKey($allocations->pluck('id')->all())->delete();
        });
    }

    private function lockOrder(int $orderId): void
    {
        Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
    }

    /** @return Collection<int, OrderItem> */
    private function orderItems(Order $order): Collection
    {
        return OrderItem::query()
            ->where('order_id', $order->getKey())
            ->orderBy('variant_id')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, OrderItemInventoryAllocation> */
    private function allocationsFor(Order $order): Collection
    {
        return OrderItemInventoryAllocation::query()
            ->whereHas('orderItem', fn (Builder $item) => $item->where('order_id', $order->getKey()))
            ->orderBy('id')
            ->get();
    }

    /** @param Collection<int, OrderItem> $items */
    private function assertNotAlreadyReserved(Collection $items): void
    {
        if (OrderItemInventoryAllocation::query()->whereIn('order_item_id', $items->pluck('id')->all())->exists()) {
            throw new ApiException(ApiErrorCode::RESOURCE_VERSION_CONFLICT, 'Inventory is already reserved for this order.', 409);
        }
    }

    /**
     * Locks every candidate row in a single global order (`id ASC`), shared by
     * reserve/release/consume to avoid deadlocks. The location-first ordering
     * used to allocate units is applied afterwards, once rows are already locked.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return Collection<int, ProductStock>
     */
    private function lockCandidateStocks(Collection $items): Collection
    {
        $variantIds = $items->pluck('variant_id')->filter()->unique()->sort()->values()->all();

        if ($variantIds === []) {
            return collect();
        }

        return ProductStock::query()
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * The given rows are already locked FOR UPDATE, so their
     * quantity/reserved_quantity are stable for this transaction; allocation is
     * validated against that locked state (no re-read can change it).
     *
     * @param  Collection<int, ProductStock>  $stocks
     */
    private function reserveItem(OrderItem $item, Collection $stocks): void
    {
        $remaining = $item->quantity;

        $rows = $stocks
            ->where('product_variant_id', $item->variant_id)
            ->sort(fn (ProductStock $left, ProductStock $right): int => [$left->warehouse_location, $left->id] <=> [$right->warehouse_location, $right->id])
            ->values();

        foreach ($rows as $stock) {
            if ($remaining < 1) {
                break;
            }

            $take = min($remaining, $stock->available_quantity);

            if ($take < 1) {
                continue;
            }

            $stock->reserved_quantity += $take;
            $stock->save();

            OrderItemInventoryAllocation::create([
                'order_item_id' => $item->getKey(),
                'product_stock_id' => $stock->getKey(),
                'quantity' => $take,
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new ApiException(ApiErrorCode::INSUFFICIENT_STOCK, 'The requested quantity is not available.', 422);
        }
    }

    /** @param Collection<int, OrderItemInventoryAllocation> $allocations */
    private function lockAllocationStocks(Collection $allocations): Collection
    {
        return ProductStock::query()
            ->whereIn('id', $allocations->pluck('product_stock_id')->unique()->sort()->values()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function applyAllocation(?ProductStock $stock, OrderItemInventoryAllocation $allocation, bool $consume): void
    {
        if ($stock === null) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The inventory reservation is inconsistent.', 409);
        }

        if ($stock->reserved_quantity < $allocation->quantity) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The inventory reservation is inconsistent.', 409);
        }

        if ($consume && $stock->quantity < $allocation->quantity) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The inventory reservation is inconsistent.', 409);
        }

        $stock->reserved_quantity -= $allocation->quantity;

        if ($consume) {
            $stock->quantity -= $allocation->quantity;
        }

        $stock->save();
    }
}
