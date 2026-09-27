<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Cart\AddCartItem;
use App\Services\Cart\CartHolder;
use App\Services\Cart\CartHolderResolver;
use App\Services\Cart\CartItemAdmission;
use App\Services\Cart\CartProjection;
use App\Services\Cart\GetOrCreateActiveCart;
use App\Services\Cart\GuestCartTransport;
use App\Services\Cart\MergeGuestCart;
use App\Services\Cart\RemoveCartItem;
use App\Services\Cart\UpdateCartItemQuantity;
use App\Services\IdempotencyService;
use App\Support\ApiErrorCode;
use App\Support\CartItemIdentifier;
use App\Support\GuestCartCredential;
use App\Support\IdempotencyKeyHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends V1Controller
{
    public function __construct(private readonly CartProjection $projection) {}

    public function show(
        Request $request,
        CartHolderResolver $resolver,
        GetOrCreateActiveCart $getOrCreate,
        GuestCartTransport $transport,
    ): JsonResponse {
        $holder = $resolver->resolve($request);
        $cart = $getOrCreate->forRead($holder);

        return $this->present($request, $cart, $holder, $transport, issueCredential: false);
    }

    public function addItem(
        AddCartItemRequest $request,
        CartHolderResolver $resolver,
        GetOrCreateActiveCart $getOrCreate,
        CartItemAdmission $admission,
        AddCartItem $addItem,
        GuestCartTransport $transport,
    ): JsonResponse {
        $holder = $resolver->resolve($request);
        [$product, $variant] = $admission->resolve((string) $request->validated('product_id'), $request->validated('variant_id'));
        $cart = $getOrCreate->forHolder($holder);

        try {
            $item = $addItem->add($cart, $product, $variant, (int) $request->validated('quantity'));
        } catch (\Exception $exception) {
            $this->discardUnreachableGuestCart($cart);

            throw $exception;
        }

        return $this->present($request, $cart, $holder, $transport, $item->wasRecentlyCreated ? 201 : 200);
    }

    public function updateItem(
        UpdateCartItemRequest $request,
        string $item,
        CartHolderResolver $resolver,
        GetOrCreateActiveCart $getOrCreate,
        UpdateCartItemQuantity $updateItem,
        GuestCartTransport $transport,
    ): JsonResponse {
        $holder = $resolver->resolve($request);
        $cart = $getOrCreate->requireExistingForHolder($holder);
        $cartItem = $this->resolveItem($cart, $item);
        $updateItem->update($cartItem, (int) $request->validated('quantity'));

        return $this->present($request, $cart, $holder, $transport);
    }

    public function removeItem(
        Request $request,
        string $item,
        CartHolderResolver $resolver,
        GetOrCreateActiveCart $getOrCreate,
        RemoveCartItem $removeItem,
    ): Response {
        $this->rejectRequestBody($request);

        $holder = $resolver->resolve($request);
        $cart = $getOrCreate->requireExistingForHolder($holder);
        $removeItem->remove($this->resolveItem($cart, $item));

        return response()->noContent()->withHeaders($this->privateHeaders());
    }

    public function merge(
        Request $request,
        GuestCartTransport $transport,
        MergeGuestCart $merge,
        IdempotencyService $idempotency,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        $key = IdempotencyKeyHeader::require($request);
        $credential = $transport->readCredential($request);

        if ($credential === null) {
            throw new ApiException(ApiErrorCode::MISSING_REQUIRED_FIELD, 'The guest cart credential is required.', 422, GuestCartTransport::HEADER);
        }

        $digest = GuestCartCredential::digest($credential);

        $outcome = $idempotency->execute(
            $actor,
            MergeGuestCart::ACTION,
            $key,
            ['source_guest_digest' => $digest],
            fn (): array => $this->projection->render($merge->merge($actor, $digest)),
        );

        return response()->json(['data' => $outcome->body])->withHeaders($this->privateHeaders());
    }

    /** CART-004 is bodyless; a body carrying fields is a strict unknown-field violation. */
    private function rejectRequestBody(Request $request): void
    {
        if ($this->hasRequestBody($request)) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'This endpoint does not accept a request body.', 422);
        }
    }

    private function hasRequestBody(Request $request): bool
    {
        if ($request->isJson()) {
            $decoded = json_decode($request->getContent(), true);

            return is_array($decoded) && $decoded !== [];
        }

        return $request->request->all() !== [];
    }

    private function discardUnreachableGuestCart(Cart $cart): void
    {
        if ($cart->wasRecentlyCreated && $cart->isGuest() && $cart->items()->count() === 0) {
            $cart->delete();
        }
    }

    private function resolveItem(Cart $cart, string $identifier): CartItem
    {
        $id = CartItemIdentifier::decode($identifier);
        $item = $id === null ? null : CartItem::query()
            ->where('cart_id', $cart->getKey())
            ->whereKey($id)
            ->first();

        if ($item === null) {
            throw new ApiException(ApiErrorCode::CART_ITEM_NOT_FOUND, 'The requested cart item was not found.', 404);
        }

        return $item;
    }

    private function present(
        Request $request,
        Cart $cart,
        CartHolder $holder,
        GuestCartTransport $transport,
        int $status = 200,
        bool $issueCredential = true,
    ): JsonResponse {
        $wasRecentlyCreated = $cart->wasRecentlyCreated;

        $response = response()
            ->json(['data' => $this->projection->render($cart)], $status)
            ->withHeaders($this->privateHeaders());

        if ($issueCredential && $holder->user === null && ! $holder->credentialSupplied && $wasRecentlyCreated && $holder->rawToken !== null) {
            return $transport->issue($request, $response, $holder->rawToken);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return ['Cache-Control' => 'private, no-cache, no-store, must-revalidate'];
    }
}
