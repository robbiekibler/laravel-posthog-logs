# Changelog

All notable changes to `laravel-posthog-logs` will be documented in this file.

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
