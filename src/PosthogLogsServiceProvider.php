<?php

namespace RobbieKibler\PosthogLogs;

use Monolog\Level;
use Monolog\Logger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PosthogLogsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('posthog-logs')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        $resolveLevel = fn (mixed $level): Level => $this->resolveLogLevel($level);

        $this->app->make('log')->extend('posthog', function ($app, array $config) use ($resolveLevel): Logger {
            $handler = new PosthogHandler(
                apiKey: $config['api_key'] ?? config('posthog-logs.api_key'),
                host: $config['host'] ?? config('posthog-logs.host'),
                serviceName: $config['service_name'] ?? config('posthog-logs.service_name'),
                environment: $config['environment'] ?? config('posthog-logs.environment'),
                level: $resolveLevel($config['level'] ?? config('posthog-logs.level')),
                batchEnabled: $config['batch']['enabled'] ?? config('posthog-logs.batch.enabled', true),
                batchMaxSize: $config['batch']['max_size'] ?? config('posthog-logs.batch.max_size', 100),
                resourceAttributes: $config['resource_attributes'] ?? config('posthog-logs.resource_attributes', []),
                httpTimeout: $config['http']['timeout'] ?? config('posthog-logs.http.timeout', 5),
                httpConnectTimeout: $config['http']['connect_timeout'] ?? config('posthog-logs.http.connect_timeout', 2),
                verifySsl: $config['http']['verify_ssl'] ?? config('posthog-logs.http.verify_ssl', true),
                enabled: $config['enabled'] ?? config('posthog-logs.enabled', true),
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
