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
        $resolveLevel = fn (mixed $level): Level => $this->resolveLogLevel($level);

        $this->app->make('log')->extend('posthog', function ($app, array $config) use ($resolveLevel): Logger {
            $get = fn (string $key, mixed $default = null): mixed => $config[$key] ?? config("posthog-logs.{$key}", $default);

            $handler = new PosthogHandler(
                apiKey: $get('api_key'),
                host: $get('host', 'us.i.posthog.com'),
                serviceName: $get('service_name', 'laravel'),
                environment: $get('environment', 'production'),
                level: $resolveLevel($get('level', 'debug')),
                enabled: $get('enabled', true),
                batchSize: $get('batch_size', 100),
                queue: $get('queue'),
                timeout: $get('timeout', 2),
                resourceAttributes: $get('resource_attributes', []),
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
