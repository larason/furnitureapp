<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cart / Guest Cart
    |--------------------------------------------------------------------------
    |
    | guest_token_key: server-managed secret used to derive the keyed
    | HMAC-SHA-256 guest_token_digest persisted on carts. Set to a long,
    | random value via GUEST_CART_TOKEN_KEY in production; never store the
    | secret in the database and never expose it to clients.
    |
    | max_item_quantity: V1 per-line cart-item quantity ceiling.
    |
    */

    'guest_token_key' => env('GUEST_CART_TOKEN_KEY', ''),

    'max_item_quantity' => 100,

];
