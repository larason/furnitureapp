<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public ENQ-001 Route Gate
    |--------------------------------------------------------------------------
    |
    | The frozen `POST /api/v1/enquiries` contract permits an optional inline
    | multipart attachment, which Phase 10.6 owns. Until then the public route
    | stays gated (501) so the API never ships partial contract behavior. This
    | is an internal gate, deliberately not environment-driven: it must never
    | be possible to expose the route from configuration. Tests opt in
    | in-process only.
    |
    */

    'route_enabled' => false,

];
