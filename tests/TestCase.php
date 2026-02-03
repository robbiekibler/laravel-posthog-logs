<?php

namespace RobbieKibler\PosthogLogs\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RobbieKibler\PosthogLogs\PosthogLogsServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PosthogLogsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('posthog-logs.api_key', 'test_api_key');
        config()->set('posthog-logs.host', 'us.i.posthog.com');
        config()->set('posthog-logs.service_name', 'test-service');
        config()->set('posthog-logs.environment', 'testing');
        config()->set('posthog-logs.enabled', false);
    }
}
