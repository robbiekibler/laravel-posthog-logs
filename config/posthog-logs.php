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

    // Set to false to disable sending logs (useful for local development)
    'enabled' => env('POSTHOG_LOGS_ENABLED', true),

    // Batch size before sending (0 to disable batching and send immediately)
    'batch_size' => env('POSTHOG_BATCH_SIZE', 100),

    // Queue name for async delivery (null to send synchronously)
    'queue' => env('POSTHOG_QUEUE'),

    // HTTP timeout in seconds
    'timeout' => env('POSTHOG_TIMEOUT', 2),

    // Custom attributes included with every log record
    'resource_attributes' => [
        // 'custom.attribute' => 'value',
    ],
];
