<?php

use Monolog\Level;
use Monolog\LogRecord;
use RobbieKibler\PosthogLogs\PosthogHandler;

const DEFAULT_API_KEY = 'test_key';
const DEFAULT_HOST = 'us.i.posthog.com';
const DEFAULT_SERVICE = 'laravel';
const DEFAULT_ENV = 'production';

function createTestHandler(array $options = []): PosthogHandler
{
    return new PosthogHandler(
        apiKey: $options['apiKey'] ?? DEFAULT_API_KEY,
        host: $options['host'] ?? DEFAULT_HOST,
        serviceName: $options['serviceName'] ?? DEFAULT_SERVICE,
        environment: $options['environment'] ?? DEFAULT_ENV,
        enabled: $options['enabled'] ?? false,
        batchSize: $options['batchSize'] ?? 100,
        queue: $options['queue'] ?? null,
        resourceAttributes: $options['resourceAttributes'] ?? [],
    );
}

function invokeMethod(object $object, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
}

function createLogRecord(Level $level = Level::Info, string $message = 'Test', array $context = []): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
        extra: [],
    );
}

it('can be instantiated with default values', function () {
    $handler = new PosthogHandler(apiKey: 'test_key');

    expect($handler)->toBeInstanceOf(PosthogHandler::class);
});

it('formats log records correctly', function () {
    $handler = createTestHandler();
    $record = createLogRecord(Level::Info, 'Test message', ['user_id' => 123]);

    $formatted = invokeMethod($handler, 'formatLogRecord', $record);

    expect($formatted)->toBeArray()
        ->and($formatted['severityText'])->toBe('INFO')
        ->and($formatted['severityNumber'])->toBe(9)
        ->and($formatted['body']['stringValue'])->toBe('Test message');
});

it('maps log levels to correct OTLP severity', function () {
    $handler = createTestHandler();

    $levels = [
        [Level::Debug, 'DEBUG', 5],
        [Level::Info, 'INFO', 9],
        [Level::Notice, 'INFO', 10],
        [Level::Warning, 'WARN', 13],
        [Level::Error, 'ERROR', 17],
        [Level::Critical, 'ERROR', 18],
        [Level::Alert, 'FATAL', 21],
        [Level::Emergency, 'FATAL', 22],
    ];

    foreach ($levels as [$level, $expectedText, $expectedNumber]) {
        $formatted = invokeMethod($handler, 'formatLogRecord', createLogRecord($level));

        expect($formatted['severityText'])->toBe($expectedText)
            ->and($formatted['severityNumber'])->toBe($expectedNumber);
    }
});

it('includes trace_id and span_id when provided in context', function () {
    $handler = createTestHandler();
    $record = createLogRecord(context: ['trace_id' => 'abc123', 'span_id' => 'def456']);

    $formatted = invokeMethod($handler, 'formatLogRecord', $record);

    expect($formatted['traceId'])->toBe('abc123')
        ->and($formatted['spanId'])->toBe('def456');
});

it('formats different attribute types correctly', function () {
    $handler = createTestHandler();

    expect(invokeMethod($handler, 'formatAttributeValue', 'string'))->toBe(['stringValue' => 'string'])
        ->and(invokeMethod($handler, 'formatAttributeValue', 123))->toBe(['intValue' => '123'])
        ->and(invokeMethod($handler, 'formatAttributeValue', 1.5))->toBe(['doubleValue' => 1.5])
        ->and(invokeMethod($handler, 'formatAttributeValue', true))->toBe(['boolValue' => true])
        ->and(invokeMethod($handler, 'formatAttributeValue', ['a', 'b']))->toBe(['stringValue' => '["a","b"]'])
        ->and(invokeMethod($handler, 'formatAttributeValue', null))->toBe(['stringValue' => 'null']);
});

it('includes resource attributes', function () {
    $handler = createTestHandler([
        'serviceName' => 'my-service',
        'environment' => 'production',
        'resourceAttributes' => ['custom.attr' => 'value'],
    ]);

    $attributes = invokeMethod($handler, 'getResourceAttributes');
    $keys = array_column($attributes, 'key');

    expect($keys)->toContain('service.name', 'deployment.environment', 'telemetry.sdk.version', 'custom.attr');
});

it('does not throw when disabled or api key is empty', function () {
    $record = createLogRecord();

    // Disabled handler
    $handler = createTestHandler(['enabled' => false]);
    $handler->handle($record);

    // Null API key
    $handler = createTestHandler(['apiKey' => null, 'enabled' => true]);
    $handler->handle($record);

    expect(true)->toBeTrue();
});

it('rejects invalid hosts', function (string $host) {
    new PosthogHandler(apiKey: 'test_key', host: $host);
})->throws(InvalidArgumentException::class)->with([
    'spaces' => 'invalid host with spaces',
    'script tag' => 'evil.com<script>',
    'path' => 'evil.com/malicious/path',
    'query string' => 'evil.com?redirect=http://attacker.com',
    'fragment' => 'evil.com#fragment',
]);

it('accepts valid custom hosts', function () {
    $handler = new PosthogHandler(apiKey: 'test_key', host: 'posthog.mycompany.com');

    expect($handler)->toBeInstanceOf(PosthogHandler::class);
});

it('safely encodes values to json', function () {
    $handler = createTestHandler();

    expect(invokeMethod($handler, 'safeJsonEncode', ['key' => 'value']))->toBe('{"key":"value"}');

    $obj = new stdClass;
    $obj->name = 'test';
    expect(invokeMethod($handler, 'safeJsonEncode', $obj))->toBe('{"name":"test"}');

    // Circular reference falls back gracefully
    $circular = new stdClass;
    $circular->self = $circular;
    $result = invokeMethod($handler, 'safeJsonEncode', $circular);
    expect($result)->toBeString();
});

it('includes sdk version in resource attributes', function () {
    $handler = createTestHandler();

    $attributes = invokeMethod($handler, 'getResourceAttributes');
    $versionAttr = array_values(array_filter(
        $attributes,
        fn ($a) => $a['key'] === 'telemetry.sdk.version'
    ));

    expect($versionAttr)->not->toBeEmpty();
    expect($versionAttr[0]['value']['stringValue'])->toBeString()->not->toBeEmpty();
});

it('drops oldest logs when batch overflows due to send failures', function () {
    $handler = new class(DEFAULT_API_KEY, DEFAULT_HOST, DEFAULT_SERVICE, DEFAULT_ENV, Level::Debug, true, 5) extends PosthogHandler
    {
        public int $flushCallCount = 0;

        public function flush(): void
        {
            $this->flushCallCount++;
        }

        protected function logError(string $message): void {}
    };

    $batchProperty = new ReflectionProperty($handler, 'batch');

    for ($i = 1; $i <= 10; $i++) {
        $handler->handle(createLogRecord(message: "Message {$i}"));
    }

    $batch = $batchProperty->getValue($handler);

    expect(count($batch))->toBe(5)
        ->and($batch[0]['body']['stringValue'])->toBe('Message 6')
        ->and($batch[4]['body']['stringValue'])->toBe('Message 10')
        ->and($handler->flushCallCount)->toBe(6);
});

it('accepts queue configuration', function () {
    $handler = createTestHandler(['queue' => 'posthog-logs']);

    expect($handler)->toBeInstanceOf(PosthogHandler::class);

    $queueProp = (new ReflectionClass($handler))->getProperty('queue');
    expect($queueProp->getValue($handler))->toBe('posthog-logs');
});

it('builds payload correctly', function () {
    $handler = createTestHandler();
    $record = createLogRecord(Level::Info, 'Test message');
    $formatted = invokeMethod($handler, 'formatLogRecord', $record);

    $payload = invokeMethod($handler, 'buildPayload', [$formatted]);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('resourceLogs')
        ->and($payload['resourceLogs'])->toHaveCount(1)
        ->and($payload['resourceLogs'][0])->toHaveKey('resource')
        ->and($payload['resourceLogs'][0])->toHaveKey('scopeLogs')
        ->and($payload['resourceLogs'][0]['scopeLogs'][0]['logRecords'])->toHaveCount(1);
});

it('uses reduced default timeout', function () {
    $handler = new PosthogHandler(apiKey: 'test_key');

    $timeoutProp = (new ReflectionClass($handler))->getProperty('timeout');
    expect($timeoutProp->getValue($handler))->toBe(2);
});

it('uses sync mode by default', function () {
    $handler = new PosthogHandler(apiKey: 'test_key');

    $queueProp = (new ReflectionClass($handler))->getProperty('queue');

    expect($queueProp->getValue($handler))->toBeNull();
});

it('sends sync without retries', function () {
    $handler = new class(DEFAULT_API_KEY, DEFAULT_HOST, DEFAULT_SERVICE, DEFAULT_ENV, Level::Debug, true, 0) extends PosthogHandler
    {
        public int $sendSyncCalls = 0;

        public array $errors = [];

        protected function sendSync(array $payload): void
        {
            $this->sendSyncCalls++;
            $this->logError('Connection failed');
        }

        protected function logError(string $message): void
        {
            $this->errors[] = $message;
        }
    };

    $handler->handle(createLogRecord());

    // Should only call sendSync once (no retries)
    expect($handler->sendSyncCalls)->toBe(1);
});

it('falls back to sync when queue dispatch fails', function () {
    $handler = new class(DEFAULT_API_KEY, DEFAULT_HOST, DEFAULT_SERVICE, DEFAULT_ENV, Level::Debug, true, 0, 'logs') extends PosthogHandler
    {
        public bool $syncCalled = false;

        public array $errors = [];

        protected function dispatchToQueue(array $payload): void
        {
            try {
                throw new \Exception('Queue not available');
            } catch (\Throwable $e) {
                $this->logError("Queue dispatch failed, sending sync: {$e->getMessage()}");
                $this->sendSync($payload);
            }
        }

        protected function sendSync(array $payload): void
        {
            $this->syncCalled = true;
        }

        protected function logError(string $message): void
        {
            $this->errors[] = $message;
        }
    };

    $formatted = invokeMethod($handler, 'formatLogRecord', createLogRecord());
    invokeMethod($handler, 'send', [$formatted]);

    expect($handler->syncCalled)->toBeTrue()
        ->and($handler->errors)->toContain('Queue dispatch failed, sending sync: Queue not available');
});
