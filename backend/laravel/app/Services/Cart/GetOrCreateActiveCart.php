<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\CartStatus;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Resolves the holder's single ACTIVE cart, lazily creating an empty one when
 * absent. Creation is safe against the active-user uniqueness race.
 */
final class GetOrCreateActiveCart
{
    public function forHolder(CartHolder $holder): Cart
    {
        return $holder->user !== null
            ? $this->forCustomer($holder->user)
            : $this->forGuest($holder);
    }

    public function forRead(CartHolder $holder): Cart
    {
        if ($holder->user !== null) {
            return $this->forCustomer($holder->user);
        }

        if ($holder->credentialSupplied) {
            return $this->requireExistingForHolder($holder);
        }

        $cart = new Cart([
            'user_id' => null,
            'guest_token_digest' => $holder->digest,
            'status' => CartStatus::ACTIVE,
        ]);
        $cart->setRelation('items', $cart->newCollection());

        return $cart;
    }

    public function requireExistingForHolder(CartHolder $holder): Cart
    {
        $cart = $holder->user !== null
            ? $this->activeCustomerCart($holder->user)
            : Cart::query()->where('guest_token_digest', $holder->digest)->where('status', CartStatus::ACTIVE)->first();

        if ($cart !== null) {
            return $cart;
        }

        if ($holder->user === null && $holder->credentialSupplied) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'The guest cart credential is invalid.', 401);
        }

        throw new ApiException(ApiErrorCode::CART_ITEM_NOT_FOUND, 'The requested cart item was not found.', 404);
    }

    private function forCustomer(User $user): Cart
    {
        $existing = $this->activeCustomerCart($user);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return Cart::create([
                'user_id' => $user->getKey(),
                'guest_token_digest' => null,
                'status' => CartStatus::ACTIVE,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->activeCustomerCart($user, lock: true) ?? throw $this->unresolvable();
        }
    }

    private function forGuest(CartHolder $holder): Cart
    {
        $existing = Cart::query()
            ->where('guest_token_digest', $holder->digest)
            ->where('status', CartStatus::ACTIVE)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($holder->credentialSupplied) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'The guest cart credential is invalid.', 401);
        }

        return Cart::create([
            'user_id' => null,
            'guest_token_digest' => $holder->digest,
            'status' => CartStatus::ACTIVE,
        ]);
    }

    private function activeCustomerCart(User $user, bool $lock = false): ?Cart
    {
        $query = Cart::query()
            ->where('user_id', $user->getKey())
            ->where('status', CartStatus::ACTIVE);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function unresolvable(): ApiException
    {
        return new ApiException(ApiErrorCode::CONFLICT, 'The active cart could not be resolved.', 409);
    }
}
