<?php

return [
    'request_only' => (bool) env('COMMERCE_REQUEST_ONLY', env('APP_ENV') === 'production'),
];
