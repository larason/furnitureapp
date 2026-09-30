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
    | as a PICKUP-only public route, so it stays gated (501) unconditionally.
    | This is an internal gate, deliberately not environment-driven: it must
    | never be possible to expose the route (and therefore the unsupported
    | DELIVERY branch) from configuration. Tests opt in in-process only.
    |
    */

    'route_enabled' => false,

];
