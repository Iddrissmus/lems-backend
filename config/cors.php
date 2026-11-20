<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| Decides which websites may call this API from a browser. Only the origins
| listed in FRONTEND_ORIGINS (comma separated, no trailing slash) are
| allowed. Requests are authenticated with a bearer token in the
| Authorization header, so cookies are never sent (supports_credentials).
|
| Example: FRONTEND_ORIGINS=https://thescnmtc-lms.fly.dev,http://localhost:3000
|
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_ORIGINS', 'https://thescnmtc-lms.fly.dev')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'Idempotency-Key'],

    // Lets the frontend read how long to wait after a 429.
    'exposed_headers' => ['Retry-After'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
