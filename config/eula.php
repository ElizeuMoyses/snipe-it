<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EULA Token Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for EULA token management and validation
    |
    */

    // Token expiry time in days
    'token_expiry_days' => env('EULA_TOKEN_EXPIRY_DAYS', 30),

    // Maximum failed validation attempts before blocking
    'max_failed_attempts' => env('EULA_MAX_FAILED_ATTEMPTS', 5),

    // Block duration in minutes after max failed attempts
    'block_duration_minutes' => env('EULA_BLOCK_DURATION_MINUTES', 30),

    // Maximum signature file size in bytes (1MB)
    'max_signature_size' => env('EULA_MAX_SIGNATURE_SIZE', 1048576),

    // Cache duration for EULA text in seconds (1 hour)
    'eula_cache_duration' => env('EULA_CACHE_DURATION', 3600),
];