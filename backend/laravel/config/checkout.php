<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public CHK-001 Route Gate
    |--------------------------------------------------------------------------
    |
    | The frozen `POST /api/v1/checkout` contract promises both PICKUP and
    | DELIVERY, but Phase 7.4 DELIVERY billing-snapshot persistence is still
    | blocked. Until that model gap is resolved the endpoint must not operate
    | as a PICKUP-only public route, so it stays gated (501) by default.
    | Internal validation tests may enable it; this is a server-side gate, not
    | an external contract flag.
    |
    */

    'route_enabled' => (bool) env('CHECKOUT_ROUTE_ENABLED', false),

];
