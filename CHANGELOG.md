# Changelog

All notable changes to `laravel-posthog-logs` will be documented in this file.

## v1.2.0 - 2026-02-03

### What's New

#### Simplified Configuration

The configuration has been dramatically simplified:

**Before (15 options):**

```php
'batch' => ['enabled' => true, 'max_size' => 100],
'http' => ['timeout' => 2, 'connect_timeout' => 1, 'verify_ssl' => true],
'queue' => ['enabled' => false, 'connection' => null, 'queue' => 'posthog-logs'],

```
**After (10 options):**

```php
'batch_size' => 100,      // 0 to disable batching
'timeout' => 2,           // connect_timeout derived automatically
'queue' => null,          // queue name to enable, null for sync

```
#### Simplified Usage

```bash
# Sync mode (default) - just set your API key
POSTHOG_API_KEY=phc_xxx

# Queue mode - add queue name
POSTHOG_QUEUE=posthog-logs

```
#### Changes

- **Config**: Reduced from 15 options to 10
- **Handler constructor**: Reduced from 17 parameters to 10
- **Job constructor**: Reduced from 6 parameters to 4
- Connect timeout is now derived from timeout (timeout / 2)
- SSL verification is always enabled (security best practice)
- Removed `queue.connection` option (uses default Laravel connection)

#### Full Changelog

https://github.com/robbiekibler/laravel-posthog-logs/compare/v1.1.0...v1.2.0

## v1.0.0 - 2026-02-03

### Initial Stable Release

Send your Laravel application logs to PostHog using the OpenTelemetry OTLP format.

#### Features

- Monolog handler for PostHog logs ingestion
- Automatic batching with configurable batch size
- Log level mapping to OpenTelemetry severity
- Trace correlation support (trace_id, span_id)
- Custom resource attributes
- Configurable HTTP timeouts and SSL verification
- Retry with exponential backoff for transient failures

### Installation

```bash
composer require robbiekibler/laravel-posthog-logs


```
## v1.1.0 - 2026-02-03

### What's New

#### Added

- `php artisan posthog:test` command to verify configuration and send a test log entry
- Optional queue-based delivery for non-blocking log sending (`POSTHOG_QUEUE_ENABLED=true`)
- New queue configuration options: `queue.enabled`, `queue.connection`, `queue.queue`

#### Changed

- Reduced default HTTP timeouts for faster failure in sync mode (5s → 2s request, 2s → 1s connect)
- Removed retry logic from sync mode (queue mode handles retries via Laravel's job retry mechanism)

### Installation

```bash
composer require robbiekibler/laravel-posthog-logs



```
### Testing Your Setup

```bash
php artisan posthog:test



```
## 1.1.0 - 2025-02-02

### Added

- `php artisan posthog:test` command to verify configuration and send a test log entry
- Optional queue-based delivery for non-blocking log sending (`POSTHOG_QUEUE_ENABLED=true`)
- New queue configuration options: `queue.enabled`, `queue.connection`, `queue.queue`

### Changed

- Reduced default HTTP timeouts for faster failure in sync mode (5s → 2s request, 2s → 1s connect)
- Removed retry logic from sync mode (queue mode handles retries via Laravel's job retry mechanism)

## 1.0.0 - 2025-02-02

- Initial release
