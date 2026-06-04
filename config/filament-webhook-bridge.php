<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    | When enabled, webhooks are captured and logged but never sent to
    | external destinations. Use in development/staging environments.
    */
    'sandbox_mode' => env('WEBHOOK_BRIDGE_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    */
    'models' => [
        'paths' => [
            app_path('Models'),
        ],

        'exclude' => [
            // Example: \App\Models\PersonalAccessToken::class,
        ],

        'cache_key' => 'webhook_bridge.models',
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Schema Analysis
    |--------------------------------------------------------------------------
    */
    'field_schema' => [
        'max_relation_depth' => 3,
        'cache_ttl' => 3600,
        'excluded_attributes' => [
            'password',
            'remember_token',
            'api_token',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'verify_ssl' => env('WEBHOOK_BRIDGE_VERIFY_SSL', true),
        'max_payload_size_mb' => 5,
        'blocked_ip_ranges' => [
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '::1/128',
            'fc00::/7',
        ],
        'allowed_schemes' => ['https', 'http'],
        'require_https_in_production' => true,
        'signature_algorithm' => 'sha256',
        'encryption_driver' => env('WEBHOOK_BRIDGE_ENCRYPTION_DRIVER', 'aes-256-cbc'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Strategy
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('WEBHOOK_BRIDGE_QUEUE_CONNECTION', config('queue.default')),
        'queue_name' => 'webhooks',
        'historical_sync_queue_name' => 'webhooks-sync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Strategy (Defaults)
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'default_max_attempts' => 3,
        'backoff_base' => 10,
        'retryable_status_codes' => [408, 429, 500, 502, 503, 504],
        'non_retryable_status_codes' => [400, 401, 403, 404, 409, 422],
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Log Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'delivery_logs_days' => 90,
        'prune_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'max_requests_per_minute' => 60,
        'max_concurrent' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Historical Sync
    |--------------------------------------------------------------------------
    */
    'historical_sync' => [
        'batch_size' => 100,
        'max_batch_size' => 1000,
        'batch_delay_seconds' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'navigation_group' => 'Integrations',
        'navigation_sort' => 80,
        'navigation_icon' => 'heroicon-o-bolt',
        'register_health_widget' => true,
        'polling_interval' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate / Authorization
    |--------------------------------------------------------------------------
    */
    'authorization' => [
        'permission_prefix' => 'webhook_bridge',
        'auto_register_gates' => true,
    ],

];
