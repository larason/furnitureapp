<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\CartStatus;
use App\Support\ConcurrentTransaction;

/**
 * CART-005 guest→authenticated cart merge (Phase 6.8). Copies the guest Cart's
 * intent into the authenticated holder's ACTIVE cart, consolidating overlapping
 * `(product_id, variant_id)` lines (clamped to CartItem::MAX_QUANTITY), then
 * retires the source guest cart (ACTIVE → INACTIVE, digest preserved).
 *
 * Guest lines are merged as intent only: no Product/Variant admission and no
 * stock validation run here, so stale guest lines survive and are flagged by
 * the normal projection. No inventory mutation, lock, or reservation occurs.
 */
final class MergeGuestCart
{
    public const ACTION = 'CART-005';

    public function __construct(private readonly GetOrCreateActiveCart $getOrCreate) {}

    public function merge(User $actor, string $sourceDigest): Cart
    {
        return ConcurrentTransaction::run(function () use ($actor, $sourceDigest): Cart {
            $source = $this->activeGuestCart($sourceDigest);
            $target = $this->getOrCreate->forHolder(CartHolder::customer($actor));

            [$lockedSource, $lockedTarget] = $this->lockPair($source, $target);

            if ($lockedSource === null || $lockedSource->status !== CartStatus::ACTIVE) {
                throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'The guest cart is no longer available.', 401);
            }

            $this->consolidate($lockedSource, $lockedTarget);
            $this->retire($lockedSource, $lockedTarget);

            return $lockedTarget;
        });
    }

    private function activeGuestCart(string $digest): Cart
    {
        $cart = Cart::query()
            ->where('guest_token_digest', $digest)
            ->where('status', CartStatus::ACTIVE)
            ->first();

        if ($cart === null) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'The guest cart credential is invalid.', 401);
        }

        return $cart;
    }

    /** @return array{0: Cart|null, 1: Cart} */
    private function lockPair(Cart $source, Cart $target): array
    {
        $locked = Cart::query()
            ->whereIn('id', [$source->getKey(), $target->getKey()])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lockedTarget = $locked->get($target->getKey());

        if (! $lockedTarget instanceof Cart) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The active cart could not be resolved.', 409);
        }

        $lockedSource = $locked->get($source->getKey());

        return [$lockedSource instanceof Cart ? $lockedSource : null, $lockedTarget];
    }

    /**
     * Runs while the source and target cart rows are locked (see merge()), so
     * concurrent merges into one target serialise here; the CartItem identity
     * unique constraint remains the final backstop.
     */
    private function consolidate(Cart $source, Cart $target): void
    {
        $sourceItems = CartItem::query()
            ->where('cart_id', $source->getKey())
            ->orderBy('id')
            ->get();

        foreach ($sourceItems as $sourceItem) {
            $targetItem = $this->targetLine($target, $sourceItem);

            if ($targetItem === null) {
                CartItem::query()->create([
                    'cart_id' => $target->getKey(),
                    'product_id' => $sourceItem->product_id,
                    'variant_id' => $sourceItem->variant_id,
                    'quantity' => min($sourceItem->quantity, CartItem::MAX_QUANTITY),
                ]);

                continue;
            }

            $targetItem->quantity = min($targetItem->quantity + $sourceItem->quantity, CartItem::MAX_QUANTITY);
            $targetItem->save();
        }
    }

    private function targetLine(Cart $target, CartItem $sourceItem): ?CartItem
    {
        $query = CartItem::query()
            ->where('cart_id', $target->getKey())
            ->where('product_id', $sourceItem->product_id)
            ->lockForUpdate();

        if ($sourceItem->variant_id === null) {
            $query->whereNull('variant_id');
        } else {
            $query->where('variant_id', $sourceItem->variant_id);
        }

        return $query->first();
    }

    private function retire(Cart $source, Cart $target): void
    {
        $source->status = CartStatus::INACTIVE;
        $source->save();

        $target->touch();
    }
}
