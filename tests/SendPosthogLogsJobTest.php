<?php

use RobbieKibler\PosthogLogs\Jobs\SendPosthogLogsJob;

function createTestJob(array $options = []): SendPosthogLogsJob
{
    return new SendPosthogLogsJob(
        endpoint: $options['endpoint'] ?? 'https://us.i.posthog.com/i/v1/logs',
        apiKey: $options['apiKey'] ?? 'test_key',
        payload: $options['payload'] ?? ['resourceLogs' => []],
        httpTimeout: $options['httpTimeout'] ?? 5,
        httpConnectTimeout: $options['httpConnectTimeout'] ?? 2,
        verifySsl: $options['verifySsl'] ?? true,
    );
}

it('can be instantiated with required parameters', function () {
    $job = createTestJob();

    expect($job)->toBeInstanceOf(SendPosthogLogsJob::class)
        ->and($job->endpoint)->toBe('https://us.i.posthog.com/i/v1/logs')
        ->and($job->apiKey)->toBe('test_key')
        ->and($job->payload)->toBe(['resourceLogs' => []])
        ->and($job->httpTimeout)->toBe(5)
        ->and($job->httpConnectTimeout)->toBe(2)
        ->and($job->verifySsl)->toBeTrue();
});

it('has correct retry configuration', function () {
    $job = createTestJob();

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([1, 5, 10]);
});

it('is serializable for queue transport', function () {
    $job = createTestJob([
        'payload' => [
            'resourceLogs' => [
                [
                    'resource' => ['attributes' => []],
                    'scopeLogs' => [['logRecords' => [['body' => 'test']]]],
                ],
            ],
        ],
    ]);

    $serialized = serialize($job);
    $unserialized = unserialize($serialized);

    expect($unserialized)->toBeInstanceOf(SendPosthogLogsJob::class)
        ->and($unserialized->endpoint)->toBe($job->endpoint)
        ->and($unserialized->apiKey)->toBe($job->apiKey)
        ->and($unserialized->payload)->toBe($job->payload);
});

it('calls failed method without throwing', function () {
    // Create a testable job subclass that captures errors instead of using error_log
    $job = new class('https://us.i.posthog.com/i/v1/logs', 'test_key', ['resourceLogs' => []]) extends SendPosthogLogsJob
    {
        public ?string $lastError = null;

        public function failed(\Throwable $e): void
        {
            $this->lastError = $e->getMessage();
        }
    };

    $exception = new Exception('Connection timeout');
    $job->failed($exception);

    expect($job->lastError)->toBe('Connection timeout');
});

it('stores all configuration for queue transport', function () {
    $job = createTestJob([
        'endpoint' => 'https://custom.posthog.com/i/v1/logs',
        'apiKey' => 'custom_api_key',
        'payload' => ['test' => 'data'],
        'httpTimeout' => 10,
        'httpConnectTimeout' => 5,
        'verifySsl' => false,
    ]);

    expect($job->endpoint)->toBe('https://custom.posthog.com/i/v1/logs')
        ->and($job->apiKey)->toBe('custom_api_key')
        ->and($job->payload)->toBe(['test' => 'data'])
        ->and($job->httpTimeout)->toBe(10)
        ->and($job->httpConnectTimeout)->toBe(5)
        ->and($job->verifySsl)->toBeFalse();
});
