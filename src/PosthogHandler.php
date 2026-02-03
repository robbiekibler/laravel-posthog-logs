<?php

namespace RobbieKibler\PosthogLogs;

use Composer\InstalledVersions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class PosthogHandler extends AbstractProcessingHandler
{
    private const PACKAGE_NAME = 'robbiekibler/laravel-posthog-logs';

    private const FALLBACK_VERSION = '1.0.0';

    private const MAX_RETRY_ATTEMPTS = 2;

    private const BATCH_OVERFLOW_MULTIPLIER = 2;

    protected Client $client;

    protected string $endpoint;

    /** @var array<int, array<string, mixed>> */
    protected array $batch = [];

    protected bool $shutdownRegistered = false;

    protected ?string $sdkVersion = null;

    /**
     * Map Monolog levels to OpenTelemetry severity numbers
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/data-model/#field-severitynumber
     */
    protected const SEVERITY_MAP = [
        Level::Debug->value => 5,      // DEBUG
        Level::Info->value => 9,       // INFO
        Level::Notice->value => 10,    // INFO2
        Level::Warning->value => 13,   // WARN
        Level::Error->value => 17,     // ERROR
        Level::Critical->value => 18,  // ERROR2
        Level::Alert->value => 21,     // FATAL
        Level::Emergency->value => 22, // FATAL2
    ];

    protected const SEVERITY_TEXT_MAP = [
        Level::Debug->value => 'DEBUG',
        Level::Info->value => 'INFO',
        Level::Notice->value => 'INFO',
        Level::Warning->value => 'WARN',
        Level::Error->value => 'ERROR',
        Level::Critical->value => 'ERROR',
        Level::Alert->value => 'FATAL',
        Level::Emergency->value => 'FATAL',
    ];

    public function __construct(
        protected ?string $apiKey,
        protected string $host = 'us.i.posthog.com',
        protected string $serviceName = 'laravel',
        protected string $environment = 'production',
        int|string|Level $level = Level::Debug,
        protected bool $batchEnabled = true,
        protected int $batchMaxSize = 100,
        /** @var array<string, mixed> */
        protected array $resourceAttributes = [],
        protected int $httpTimeout = 5,
        protected int $httpConnectTimeout = 2,
        protected bool $verifySsl = true,
        protected bool $enabled = true,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        $this->validateHost($this->host);
        $this->endpoint = "https://{$this->host}/i/v1/logs";
        $this->sdkVersion = $this->resolvePackageVersion();

        $this->client = new Client([
            'timeout' => $this->httpTimeout,
            'connect_timeout' => $this->httpConnectTimeout,
            'verify' => $this->verifySsl,
        ]);
    }

    protected function validateHost(string $host): void
    {
        $url = "https://{$host}";

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid PostHog host: {$host}");
        }

        if (preg_match('/[<>"\'\s\/\?&#]/', $host)) {
            throw new InvalidArgumentException("Invalid characters in PostHog host: {$host}");
        }
    }

    protected function resolvePackageVersion(): string
    {
        if (class_exists(InstalledVersions::class)) {
            try {
                $version = InstalledVersions::getVersion(self::PACKAGE_NAME);
                if ($version !== null) {
                    return $version;
                }
            } catch (\Throwable) {
                // Fall through to default
            }
        }

        return self::FALLBACK_VERSION;
    }

    protected function write(LogRecord $record): void
    {
        if (! $this->enabled || empty($this->apiKey)) {
            return;
        }

        $logRecord = $this->formatLogRecord($record);

        if ($this->batchEnabled) {
            $this->batch[] = $logRecord;

            // Prevent unbounded memory growth if sends are failing
            $maxBatchSize = $this->batchMaxSize * self::BATCH_OVERFLOW_MULTIPLIER;
            if (count($this->batch) >= $maxBatchSize) {
                // Drop oldest logs to make room
                array_splice($this->batch, 0, $this->batchMaxSize);
                $this->logError('Batch overflow: dropped oldest logs due to send failures');
            }

            if (count($this->batch) >= $this->batchMaxSize) {
                $this->flush();
            } else {
                $this->registerShutdown();
            }
        } else {
            $this->send([$logRecord]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatLogRecord(LogRecord $record): array
    {
        $timestamp = (int) ($record->datetime->format('U.u') * 1_000_000_000);

        $attributes = $this->formatAttributes($record->context);

        if (! empty($record->extra)) {
            $attributes = array_merge($attributes, $this->formatAttributes($record->extra, 'extra.'));
        }

        $attributes[] = [
            'key' => 'log.channel',
            'value' => ['stringValue' => $record->channel],
        ];

        $logEntry = [
            'timeUnixNano' => (string) $timestamp,
            'observedTimeUnixNano' => (string) $timestamp,
            'severityNumber' => self::SEVERITY_MAP[$record->level->value],
            'severityText' => self::SEVERITY_TEXT_MAP[$record->level->value],
            'body' => ['stringValue' => $record->message],
            'attributes' => $attributes,
        ];

        if (isset($record->context['trace_id'])) {
            $logEntry['traceId'] = $record->context['trace_id'];
        }

        if (isset($record->context['span_id'])) {
            $logEntry['spanId'] = $record->context['span_id'];
        }

        return $logEntry;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function formatAttributes(array $context, string $prefix = ''): array
    {
        $attributes = [];

        foreach ($context as $key => $value) {
            if (in_array($key, ['trace_id', 'span_id'], true)) {
                continue;
            }

            $attributes[] = [
                'key' => $prefix.$key,
                'value' => $this->formatAttributeValue($value),
            ];
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAttributeValue(mixed $value): array
    {
        return match (true) {
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_array($value) => ['stringValue' => $this->safeJsonEncode($value)],
            is_object($value) => ['stringValue' => $this->safeJsonEncode($value)],
            is_null($value) => ['stringValue' => 'null'],
            default => ['stringValue' => (string) $value],
        };
    }

    protected function safeJsonEncode(mixed $value): string
    {
        $result = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);

        if ($result === false) {
            return is_object($value)
                ? sprintf('[object %s]', get_class($value))
                : '[encoding failed]';
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getResourceAttributes(): array
    {
        $attributes = [
            [
                'key' => 'service.name',
                'value' => ['stringValue' => $this->serviceName],
            ],
            [
                'key' => 'deployment.environment',
                'value' => ['stringValue' => $this->environment],
            ],
            [
                'key' => 'telemetry.sdk.name',
                'value' => ['stringValue' => 'laravel-posthog-logs'],
            ],
            [
                'key' => 'telemetry.sdk.language',
                'value' => ['stringValue' => 'php'],
            ],
            [
                'key' => 'telemetry.sdk.version',
                'value' => ['stringValue' => $this->sdkVersion],
            ],
        ];

        foreach ($this->resourceAttributes as $key => $value) {
            $attributes[] = [
                'key' => $key,
                'value' => $this->formatAttributeValue($value),
            ];
        }

        return $attributes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $logRecords
     */
    protected function send(array $logRecords): void
    {
        if (empty($logRecords)) {
            return;
        }

        $payload = [
            'resourceLogs' => [
                [
                    'resource' => [
                        'attributes' => $this->getResourceAttributes(),
                    ],
                    'scopeLogs' => [
                        [
                            'scope' => [
                                'name' => 'laravel-posthog-logs',
                                'version' => $this->sdkVersion,
                            ],
                            'logRecords' => $logRecords,
                        ],
                    ],
                ],
            ],
        ];

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            try {
                $this->client->post($this->endpoint, [
                    'json' => $payload,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$this->apiKey}",
                    ],
                ]);

                return; // Success, exit the retry loop
            } catch (GuzzleException $e) {
                $lastException = $e;

                // Don't retry on client errors (4xx) except 429 (rate limit)
                if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                    break;
                }

                // Wait before retry with exponential backoff
                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    usleep($attempt * 100_000); // 100ms, 200ms
                }
            }
        }

        // Log failure to PHP's error log to avoid infinite loops
        // $lastException is guaranteed to be set here since we only reach
        // this point if we broke out of the loop or exhausted retries
        /** @var GuzzleException $lastException */
        $this->logError(sprintf(
            'Failed to send %d log(s) after %d attempts: %s',
            count($logRecords),
            self::MAX_RETRY_ATTEMPTS,
            $lastException->getMessage()
        ));
    }

    protected function logError(string $message): void
    {
        error_log("[PosthogLogs] {$message}");
    }

    public function flush(): void
    {
        if (! empty($this->batch)) {
            $this->send($this->batch);
            $this->batch = [];
        }
    }

    protected function registerShutdown(): void
    {
        if (! $this->shutdownRegistered) {
            register_shutdown_function([$this, 'flush']);
            $this->shutdownRegistered = true;
        }
    }

    public function close(): void
    {
        $this->flush();
        parent::close();
    }
}
