# Changelog

All notable changes to `laravel-posthog-logs` will be documented in this file.

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
