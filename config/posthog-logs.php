<?php

return [
    // Your PostHog project API key (starts with phc_)
    'api_key' => env('POSTHOG_API_KEY'),

    // PostHog host: us.i.posthog.com, eu.i.posthog.com, or self-hosted URL
    'host' => env('POSTHOG_HOST', 'us.i.posthog.com'),

    // Service name for log identification
    'service_name' => env('POSTHOG_SERVICE_NAME', env('APP_NAME', 'laravel')),

    // Deployment environment (production, staging, development)
    'environment' => env('POSTHOG_ENVIRONMENT', env('APP_ENV', 'production')),

    // Minimum log level: debug, info, notice, warning, error, critical, alert, emergency
    'level' => env('POSTHOG_LOG_LEVEL', 'debug'),

    // Batching reduces HTTP requests for better performance
    'batch' => [
        'enabled' => env('POSTHOG_BATCH_ENABLED', true),
        'max_size' => env('POSTHOG_BATCH_MAX_SIZE', 100),
    ],

    // Custom attributes included with every log record
    'resource_attributes' => [
        // 'custom.attribute' => 'value',
    ],

    // HTTP client settings (reduced defaults for faster fail in sync mode)
    'http' => [
        'timeout' => env('POSTHOG_HTTP_TIMEOUT', 2),
        'connect_timeout' => env('POSTHOG_HTTP_CONNECT_TIMEOUT', 1),
        'verify_ssl' => env('POSTHOG_HTTP_VERIFY_SSL', true),
    ],

    // Optional queue-based delivery for non-blocking log sending
    'queue' => [
        'enabled' => env('POSTHOG_QUEUE_ENABLED', false),
        'connection' => env('POSTHOG_QUEUE_CONNECTION'),
        'queue' => env('POSTHOG_QUEUE_NAME', 'posthog-logs'),
    ],

    // Set to false to disable sending logs (useful for local development)
    'enabled' => env('POSTHOG_LOGS_ENABLED', true),
];
