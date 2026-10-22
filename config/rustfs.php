<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | The connection used by the RustFs facade when no name is given.
    |
    */

    'default' => env('RUSTFS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Every connection points at one RustFS deployment. The same credentials are
    | used for the S3 API and the Admin API (requests are AWS SigV4 signed).
    | RustFS ships with "rustfsadmin" / "rustfsadmin" - change it in production.
    |
    */

    'connections' => [

        'default' => [
            'endpoint' => env('RUSTFS_ENDPOINT', 'http://127.0.0.1:9000'),
            'key' => env('RUSTFS_ACCESS_KEY', 'rustfsadmin'),
            'secret' => env('RUSTFS_SECRET_KEY', 'rustfsadmin'),
            'token' => env('RUSTFS_SESSION_TOKEN'),
            'region' => env('RUSTFS_REGION', 'us-east-1'),
            'bucket' => env('RUSTFS_BUCKET'),

            // RustFS serves buckets path-style (http://host:9000/bucket/key).
            'use_path_style_endpoint' => (bool) env('RUSTFS_PATH_STYLE', true),

            // Admin API prefix. "/rustfs/admin" takes plain JSON bodies.
            'admin_prefix' => env('RUSTFS_ADMIN_PREFIX', '/rustfs/admin'),

            // Admin calls answered with 503 (e.g. bucket usage not computed yet) are retried.
            'admin_retries' => (int) env('RUSTFS_ADMIN_RETRIES', 3),
            'admin_retry_delay_ms' => (int) env('RUSTFS_ADMIN_RETRY_DELAY_MS', 1000),

            // Guzzle request options shared by S3 and Admin calls.
            'http' => [
                'timeout' => (float) env('RUSTFS_TIMEOUT', 30),
                'connect_timeout' => (float) env('RUSTFS_CONNECT_TIMEOUT', 5),
                'verify' => (bool) env('RUSTFS_VERIFY_TLS', true),
            ],
        ],

    ],

];
