# Laravel PostHog Logs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/robbiekibler/laravel-posthog-logs.svg?style=flat-square)](https://packagist.org/packages/robbiekibler/laravel-posthog-logs)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/robbiekibler/laravel-posthog-logs/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/robbiekibler/laravel-posthog-logs/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/robbiekibler/laravel-posthog-logs.svg?style=flat-square)](https://packagist.org/packages/robbiekibler/laravel-posthog-logs)

Send your Laravel application logs to [PostHog](https://posthog.com/docs/logs) using the OpenTelemetry OTLP format. Just add the package, configure your environment variables, and your logs are automatically available in PostHog.

## Installation

Install the package via Composer:

```bash
composer require robbiekibler/laravel-posthog-logs
```

## Configuration

### Quick Start

Add these environment variables to your `.env` file:

```env
POSTHOG_API_KEY=phc_your_project_api_key
POSTHOG_HOST=us.i.posthog.com
```

> **Security Note:** Use a **project API key** (starts with `phc_`), not a personal API key. Project API keys have limited scope and are safe for server-side usage.

Then add the `posthog` channel to your `config/logging.php`:

```php
'channels' => [
    // ... other channels

    'posthog' => [
        'driver' => 'posthog',
    ],
],
```

To use PostHog as your default log channel, or as part of a stack:

```php
// Use PostHog as default
'default' => env('LOG_CHANNEL', 'posthog'),

// Or add to a stack
'stack' => [
    'driver' => 'stack',
    'channels' => ['single', 'posthog'],
],
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `POSTHOG_API_KEY` | Your PostHog project API key | (required) |
| `POSTHOG_HOST` | PostHog host (`us.i.posthog.com`, `eu.i.posthog.com`, or self-hosted) | `us.i.posthog.com` |
| `POSTHOG_SERVICE_NAME` | Service name for log identification | `APP_NAME` |
| `POSTHOG_ENVIRONMENT` | Deployment environment | `APP_ENV` |
| `POSTHOG_LOG_LEVEL` | Minimum log level to send | `debug` |
| `POSTHOG_LOGS_ENABLED` | Enable/disable sending logs | `true` |
| `POSTHOG_BATCH_ENABLED` | Enable batching of logs | `true` |
| `POSTHOG_BATCH_MAX_SIZE` | Maximum logs per batch | `100` |

### Publishing Config (Optional)

To customize all options, publish the config file:

```bash
php artisan vendor:publish --tag="posthog-logs-config"
```

## Testing Your Configuration

Verify your setup is working by running the test command:

```bash
php artisan posthog:test
```

This will display your current configuration and send a test log entry to PostHog. You can customize the test message:

```bash
php artisan posthog:test --message="Hello from my app!"
```

## Usage

Once configured, use Laravel's standard logging:

```php
use Illuminate\Support\Facades\Log;

// Basic logging
Log::info('User logged in');
Log::warning('Rate limit approaching');
Log::error('Payment failed', ['order_id' => 123]);

// With context
Log::channel('posthog')->info('Order created', [
    'order_id' => $order->id,
    'customer_id' => $customer->id,
    'total' => $order->total,
]);
```

### Trace Correlation

If you're using distributed tracing, you can include trace context:

```php
Log::info('Processing request', [
    'trace_id' => $traceId,
    'span_id' => $spanId,
    'user_id' => $userId,
]);
```

### Log Levels

The package maps Laravel/Monolog log levels to OpenTelemetry severity:

| Laravel Level | OTLP Severity |
|---------------|---------------|
| debug | DEBUG (5) |
| info | INFO (9) |
| notice | INFO (10) |
| warning | WARN (13) |
| error | ERROR (17) |
| critical | ERROR (18) |
| alert | FATAL (21) |
| emergency | FATAL (22) |

## Viewing Logs in PostHog

1. Go to your PostHog dashboard
2. Navigate to **Logs** in the sidebar
3. Filter by service name, environment, or log level
4. Click on individual logs to see full context and attributes

## Advanced Configuration

### Channel-Level Overrides

Override settings per channel in `config/logging.php`:

```php
'posthog' => [
    'driver' => 'posthog',
    'level' => 'warning',  // Only send warnings and above
    'service_name' => 'my-api',
    'environment' => 'staging',
],
```

### Custom Resource Attributes

Add custom attributes to all logs via config:

```php
// config/posthog-logs.php
'resource_attributes' => [
    'team.name' => 'backend',
    'version' => '1.2.3',
],
```

### Disable in Tests

```env
# .env.testing
POSTHOG_LOGS_ENABLED=false
```

### Performance Considerations

Logs are sent to PostHog via synchronous HTTP requests. The package includes:

- **Batching**: Logs are batched (default: 100 per batch) to reduce HTTP overhead
- **Timeouts**: Short timeouts (5s request, 2s connect) to prevent blocking
- **Retries**: Automatic retry with exponential backoff for transient failures
- **Overflow Protection**: Oldest logs are dropped if the batch overflows due to send failures

For high-throughput applications, consider:
- Using a log stack with a fast local channel (e.g., `single`) as primary
- Adjusting batch size via `POSTHOG_BATCH_MAX_SIZE`
- Setting a higher minimum log level in production via `POSTHOG_LOG_LEVEL`

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Robbie Kibler](https://github.com/robbiekibler)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
