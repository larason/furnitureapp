<?php

return [
    'secret_key' => env('CLERK_SECRET_KEY'),
    'jwt_key' => env('CLERK_JWT_KEY'),
    'issuer' => env('CLERK_ISSUER'),
    'authorized_parties' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CLERK_AUTHORIZED_PARTIES', '')),
    ))),
    'audiences' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CLERK_AUDIENCES', '')),
    ))),
];
