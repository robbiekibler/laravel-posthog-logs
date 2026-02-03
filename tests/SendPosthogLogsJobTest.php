<?php

use RobbieKibler\PosthogLogs\Jobs\SendPosthogLogsJob;

const JOB_TEST_ENDPOINT = 'https://us.i.posthog.com/i/v1/logs';
const JOB_TEST_API_KEY = 'test_key';
const JOB_TEST_PAYLOAD = ['resourceLogs' => []];
const JOB_TEST_TIMEOUT = 5;

function createTestJob(array $options = []): SendPosthogLogsJob
{
    return new SendPosthogLogsJob(
        endpoint: $options['endpoint'] ?? JOB_TEST_ENDPOINT,
        apiKey: $options['apiKey'] ?? JOB_TEST_API_KEY,
        payload: $options['payload'] ?? JOB_TEST_PAYLOAD,
        timeout: $options['timeout'] ?? JOB_TEST_TIMEOUT,
    );
}

it('can be instantiated with required parameters', function () {
    $job = createTestJob();

    expect($job)->toBeInstanceOf(SendPosthogLogsJob::class)
        ->and($job->endpoint)->toBe(JOB_TEST_ENDPOINT)
        ->and($job->apiKey)->toBe(JOB_TEST_API_KEY)
        ->and($job->payload)->toBe(JOB_TEST_PAYLOAD)
        ->and($job->timeout)->toBe(JOB_TEST_TIMEOUT);
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
    $job = new class(JOB_TEST_ENDPOINT, JOB_TEST_API_KEY, JOB_TEST_PAYLOAD) extends SendPosthogLogsJob
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
        'timeout' => 10,
    ]);

    expect($job->endpoint)->toBe('https://custom.posthog.com/i/v1/logs')
        ->and($job->apiKey)->toBe('custom_api_key')
        ->and($job->payload)->toBe(['test' => 'data'])
        ->and($job->timeout)->toBe(10);
});
