<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'api' => (int) env('API_RATE_LIMIT', 60),
        'auth' => (int) env('API_AUTH_RATE_LIMIT', 5),
        'uploads' => (int) env('API_UPLOAD_RATE_LIMIT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Access tokens
    |--------------------------------------------------------------------------
    |
    | Lifetime of the Sanctum personal access tokens issued on login / register.
    |
    */

    'token_ttl_minutes' => (int) env('API_TOKEN_TTL', 60 * 24),

    /*
    |--------------------------------------------------------------------------
    | File uploads
    |--------------------------------------------------------------------------
    |
    | SVG is intentionally not allowed: it can carry scripts (stored XSS).
    |
    */

    'uploads' => [
        'disk' => env('UPLOADS_DISK', 'public'),
        'directory' => 'uploads',
        'max_kb' => (int) env('UPLOADS_MAX_KB', 10 * 1024),
        'mimes' => explode(',', env('UPLOADS_MIMES', 'jpg,jpeg,png,gif,webp,mp4,webm,mov,pdf')),
    ],

];
