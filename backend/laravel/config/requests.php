<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public REQ-001 Route Gate
    |--------------------------------------------------------------------------
    |
    | The frozen `POST /api/v1/requests` contract is implemented (Phase 10.1),
    | but Phase 10.2 full validation, Phase 10.4 MADE_TO_ORDER linked-product
    | eligibility, and Phase 10.6 attachment handling are not. Until those
    | prerequisites land the public route stays gated (501) so the API never
    | ships partial contract behavior. This is an internal gate, deliberately
    | not environment-driven: it must never be possible to expose the route
    | from configuration. Tests opt in in-process only.
    |
    */

    'route_enabled' => false,

];
