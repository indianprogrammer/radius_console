<?php

return [
    // Base URL of the external RADIUS management server (SRD §4.1).
    'base_url' => env('RADIUS_API_BASE', 'http://127.0.0.1:8001/api'),

    // Credentials used to obtain the JWT via POST /api/auth/login (SRD §4.1).
    // In production these come from the secrets vault, never hardcoded.
    'username' => env('RADIUS_API_USER', 'manoj'),
    'password' => env('RADIUS_API_PASS', 'test1'),

    // Circuit-breaker / retry (SRD §4.1 resilience).
    'timeout_sec' => 5,
    'retry_attempts' => 3,
    'retry_delay_ms' => 300,
];
