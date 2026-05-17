<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'key' => env('NOTIFICATION_API_KEY'),
        'max_batch_size' => (int) env('NOTIFICATION_MAX_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    | Maximum number of provider calls per second per channel.
    */
    'rate_limit' => [
        'per_second' => (int) env('NOTIFICATION_RATE_LIMIT_PER_SECOND', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'ttl_hours' => (int) env('NOTIFICATION_IDEMPOTENCY_TTL_HOURS', 24),
        'header' => 'Idempotency-Key',
    ],

    /*
    |--------------------------------------------------------------------------
    | External webhook.* provider behaviour
    |--------------------------------------------------------------------------
    | Free webhook.site tiers often answer HTTP 429. The job honours
    | Retry-After when present and releases back to the queue instead of failing.
    */
    'provider' => [
        'http_429_max_rounds_per_notification' => (int) env('NOTIFICATION_PROVIDER_429_MAX_ROUNDS', 50),
        'http_429_default_retry_seconds' => (int) env('NOTIFICATION_PROVIDER_429_RETRY_DEFAULT', 120),
        'http_429_retry_seconds_cap' => (int) env('NOTIFICATION_PROVIDER_429_RETRY_CAP', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry policy
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 5,
        'backoff_seconds' => [2, 5, 15, 60, 300],
        'jitter_percent' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content limits per channel
    |--------------------------------------------------------------------------
    */
    'content_limits' => [
        'sms' => 160,
        'email' => 100_000,
        'push' => 4_096,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue names per priority
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'high' => 'notifications-high',
        'normal' => 'notifications-normal',
        'low' => 'notifications-low',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'window_seconds' => 300,
    ],

];
