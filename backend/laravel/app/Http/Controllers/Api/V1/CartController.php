<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Services\Cart\CartHolderResolver;
use App\Services\Cart\GetOrCreateActiveCart;
use App\Services\Cart\GuestCartTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends V1Controller
{
    public function show(
        Request $request,
        CartHolderResolver $resolver,
        GetOrCreateActiveCart $getOrCreate,
        GuestCartTransport $transport,
    ): JsonResponse {
        $holder = $resolver->resolve($request);
        $cart = $getOrCreate->forHolder($holder);
        $this->loadCart($cart);

        $response = response()
            ->json(['data' => (new CartResource($cart))->resolve()])
            ->withHeaders(['Cache-Control' => 'private, no-cache, no-store, must-revalidate']);

        if ($holder->user === null && ! $holder->credentialSupplied && $holder->rawToken !== null) {
            return $transport->issue($request, $response, $holder->rawToken);
        }

        return $response;
    }

    public function addItem(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateItem(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function removeItem(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function merge(): JsonResponse
    {
        return $this->notImplemented();
    }

    private function loadCart(Cart $cart): void
    {
        $cart->load([
            'items' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'items.product' => fn ($query) => $query->withTrashed(),
            'items.product.category',
            'items.product.primaryImage',
            'items.product.variants',
            'items.product.variants.stocks',
            'items.variant',
            'items.variant.stocks',
        ]);
    }
}
