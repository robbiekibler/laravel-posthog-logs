<?php

use Illuminate\Support\Facades\Log;
use Monolog\Level;
use RobbieKibler\PosthogLogs\PosthogLogsServiceProvider;

function resolveLevel(mixed $level): Level
{
    $provider = new PosthogLogsServiceProvider(app());
    $method = new ReflectionMethod($provider, 'resolveLogLevel');

    return $method->invoke($provider, $level);
}

it('registers the posthog log channel', function () {
    expect(app('log')->getChannels())->toBeArray();
});

it('config values are set correctly', function () {
    expect(config('posthog-logs.api_key'))->toBe('test_api_key')
        ->and(config('posthog-logs.host'))->toBe('us.i.posthog.com')
        ->and(config('posthog-logs.service_name'))->toBe('test-service')
        ->and(config('posthog-logs.environment'))->toBe('testing');
});

it('can create a posthog channel', function () {
    expect(Log::channel('posthog'))->toBeInstanceOf(\Psr\Log\LoggerInterface::class);
});

it('resolves log levels correctly', function () {
    // String levels (case-insensitive)
    expect(resolveLevel('debug'))->toBe(Level::Debug)
        ->and(resolveLevel('INFO'))->toBe(Level::Info)
        ->and(resolveLevel('Warning'))->toBe(Level::Warning)
        ->and(resolveLevel('error'))->toBe(Level::Error);

    // Level enum passthrough
    expect(resolveLevel(Level::Critical))->toBe(Level::Critical);

    // Integer levels
    expect(resolveLevel(100))->toBe(Level::Debug)
        ->and(resolveLevel(200))->toBe(Level::Info)
        ->and(resolveLevel(300))->toBe(Level::Warning)
        ->and(resolveLevel(400))->toBe(Level::Error);
});

it('falls back to debug for invalid log levels', function () {
    expect(resolveLevel('invalid'))->toBe(Level::Debug)
        ->and(resolveLevel(null))->toBe(Level::Debug)
        ->and(resolveLevel([]))->toBe(Level::Debug)
        ->and(resolveLevel(999))->toBe(Level::Debug);
});
