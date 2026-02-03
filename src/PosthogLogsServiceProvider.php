<?php

namespace RobbieKibler\PosthogLogs;

use Monolog\Level;
use Monolog\Logger;
use RobbieKibler\PosthogLogs\Commands\TestLogCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PosthogLogsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('posthog-logs')
            ->hasConfigFile()
            ->hasCommand(TestLogCommand::class);
    }

    public function packageBooted(): void
    {
        $this->app->make('log')->extend('posthog', function ($app, array $config): Logger {
            $get = fn (string $key, mixed $default = null): mixed => $config[$key] ?? config("posthog-logs.{$key}", $default);

            $handler = new PosthogHandler(
                apiKey: $get('api_key'),
                host: $get('host'),
                serviceName: $get('service_name'),
                environment: $get('environment'),
                level: $this->resolveLogLevel($get('level')),
                batchEnabled: $config['batch']['enabled'] ?? config('posthog-logs.batch.enabled', true),
                batchMaxSize: $config['batch']['max_size'] ?? config('posthog-logs.batch.max_size', 100),
                resourceAttributes: $get('resource_attributes', []),
                httpTimeout: $config['http']['timeout'] ?? config('posthog-logs.http.timeout', 2),
                httpConnectTimeout: $config['http']['connect_timeout'] ?? config('posthog-logs.http.connect_timeout', 1),
                verifySsl: $config['http']['verify_ssl'] ?? config('posthog-logs.http.verify_ssl', true),
                enabled: $get('enabled', true),
                useQueue: $config['queue']['enabled'] ?? config('posthog-logs.queue.enabled', false),
                queueConnection: $config['queue']['connection'] ?? config('posthog-logs.queue.connection'),
                queueName: $config['queue']['queue'] ?? config('posthog-logs.queue.queue', 'posthog-logs'),
            );

            return new Logger('posthog', [$handler]);
        });
    }

    protected function resolveLogLevel(mixed $level): Level
    {
        if ($level instanceof Level) {
            return $level;
        }

        try {
            if (is_string($level)) {
                return Level::fromName($level);
            }

            if (is_int($level)) {
                /** @var 100|200|250|300|400|500|550|600 $level */
                return Level::fromValue($level);
            }
        } catch (\ValueError|\UnhandledMatchError) {
            // Fall through to default
        }

        return Level::Debug;
    }
}
