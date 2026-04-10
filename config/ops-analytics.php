<?php

declare(strict_types=1);

return [
    'cache' => [
        'enabled' => env('OPS_ANALYTICS_CACHE_ENABLED', true),
        'ttl' => (int) env('OPS_ANALYTICS_CACHE_TTL', 300),
        'store' => env('OPS_ANALYTICS_CACHE_STORE'),
    ],

    'audit' => [
        'enabled' => env('OPS_ANALYTICS_AUDIT_ENABLED', true),
        'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER', 'activitylog'),
        'log_name' => env('OPS_ANALYTICS_AUDIT_LOG_NAME', 'ops-analytics'),
    ],

    'dispatch' => [
        'queue_connection' => env('OPS_ANALYTICS_QUEUE_CONNECTION'),
        'queue_name' => env('OPS_ANALYTICS_QUEUE_NAME'),
        'sync_fallback' => env('OPS_ANALYTICS_SYNC_FALLBACK', true),
    ],

    'default_tracker' => [
        'enabled' => env('OPS_ANALYTICS_DEFAULT_TRACKER_ENABLED', true),
        'key' => env('OPS_ANALYTICS_DEFAULT_TRACKER_KEY', 'default-runtime'),
        'name' => env('OPS_ANALYTICS_DEFAULT_TRACKER_NAME', 'Default Runtime Tracker'),
        'driver' => env('OPS_ANALYTICS_DEFAULT_TRACKER_DRIVER', 'default-runtime'),
        'consent_mode' => env('OPS_ANALYTICS_DEFAULT_TRACKER_CONSENT_MODE', 'optional'),
    ],

    'request_capture' => [
        'enabled' => env('OPS_ANALYTICS_REQUEST_CAPTURE_ENABLED', true),
    ],

    'delivery' => [
        'warning_minutes' => (int) env('OPS_ANALYTICS_WARNING_MINUTES', 30),
        'failed_minutes' => (int) env('OPS_ANALYTICS_FAILED_MINUTES', 120),
        'max_consecutive_failures' => (int) env('OPS_ANALYTICS_MAX_CONSECUTIVE_FAILURES', 3),
    ],

    'consent' => [
        'integration_required' => env('OPS_ANALYTICS_CONSENT_REQUIRED', false),
        'default_mode' => env('OPS_ANALYTICS_CONSENT_DEFAULT_MODE', 'runtime'),
        'default_source' => env('OPS_ANALYTICS_CONSENT_SOURCE', 'runtime'),
    ],

    'safety' => [
        'redact_keys' => [
            'api_key',
            'token',
            'secret',
            'authorization',
            'cookie',
            'payload',
            'body',
            'request',
            'response',
        ],
        'forbid_sensitive_defaults' => env('OPS_ANALYTICS_FORBID_SENSITIVE_DEFAULTS', true),
    ],

    'unsupported' => [
        'exclude_from_aggregation' => env('OPS_ANALYTICS_EXCLUDE_UNSUPPORTED', false),
    ],
];
