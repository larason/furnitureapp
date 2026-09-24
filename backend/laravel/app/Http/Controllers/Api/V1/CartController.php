<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Cart\AddCartItem;
use App\Services\Cart\CartHolder;
use App\Services\Cart\CartHolderResolver;
use App\Services\Cart\CartItemAdmission;
use App\Services\Cart\CartStockRevalidator;
use App\Services\Cart\GetOrCreateActiveCart;
use App\Services\Cart\GuestCartTransport;
use App\Services\Cart\RemoveCartItem;
use App\Services\Cart\UpdateCartItemQuantity;
use App\Support\ApiErrorCode;
use App\Support\CartItemIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends V1Controller
{
    public function __construct(private readonly CartStockRevalidator $revalidator) {}

    public function show(
        Request $request,
        CartHolderResolver $resolver,
        GetOrCreateActiveCart $getOrCreate,
        GuestCartTransport $transport,
    ): JsonResponse {
        $holder = $resolver->resolve($request);
        $cart = $getOrCreate->forHolder($holder);

        return $this->present($request, $cart, $holder, $transport);
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

    public function merge(): JsonResponse
    {
        return $this->notImplemented();
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
    ): JsonResponse {
        $wasRecentlyCreated = $cart->wasRecentlyCreated;
        $this->loadCart($cart);

        $response = response()
            ->json(['data' => (new CartResource($cart, $this->revalidator->revalidate($cart)))->resolve()], $status)
            ->withHeaders($this->privateHeaders());

        if ($holder->user === null && ! $holder->credentialSupplied && $wasRecentlyCreated && $holder->rawToken !== null) {
            return $transport->issue($request, $response, $holder->rawToken);
        }

        return $response;
    }

    private function loadCart(Cart $cart): void
    {
        $cart->load([
            'items' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'items.product' => fn ($query) => $query->withTrashed(),
            'items.product.category',
            'items.product.primaryImage',
            'items.product.variants',
            'items.variant',
        ]);
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return ['Cache-Control' => 'private, no-cache, no-store, must-revalidate'];
    }
}
