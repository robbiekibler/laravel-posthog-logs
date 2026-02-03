<?php

namespace RobbieKibler\PosthogLogs\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class TestLogCommand extends Command
{
    protected $signature = 'posthog:test
                            {--message=Test log from Laravel PostHog Logs : Custom test message}';

    protected $description = 'Send a test log entry to PostHog to verify configuration';

    public function handle(): int
    {
        $this->info('PostHog Logs Configuration Test');
        $this->line('');

        $apiKey = config('posthog-logs.api_key');
        $host = config('posthog-logs.host', 'us.i.posthog.com');
        $serviceName = config('posthog-logs.service_name', config('app.name', 'laravel'));
        $environment = config('posthog-logs.environment', config('app.env', 'production'));
        $enabled = config('posthog-logs.enabled', true);

        $this->table(['Setting', 'Value'], [
            ['API Key', $apiKey ? substr($apiKey, 0, 10).'...' : '<error>NOT SET</error>'],
            ['Host', $host],
            ['Service Name', $serviceName],
            ['Environment', $environment],
            ['Enabled', $enabled ? 'Yes' : 'No'],
        ]);

        $this->line('');

        if (! $apiKey) {
            $this->error('POSTHOG_API_KEY is not set. Please add it to your .env file.');

            return self::FAILURE;
        }

        if (! $enabled) {
            $this->warn('PostHog logs are disabled (POSTHOG_LOGS_ENABLED=false).');
            $this->line('Sending test anyway to verify connectivity...');
            $this->line('');
        }

        $message = $this->option('message');
        $endpoint = "https://{$host}/i/v1/logs";
        $timestamp = (int) (microtime(true) * 1_000_000_000);

        $payload = [
            'resourceLogs' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => $serviceName]],
                            ['key' => 'deployment.environment', 'value' => ['stringValue' => $environment]],
                            ['key' => 'telemetry.sdk.name', 'value' => ['stringValue' => 'laravel-posthog-logs']],
                            ['key' => 'telemetry.sdk.language', 'value' => ['stringValue' => 'php']],
                        ],
                    ],
                    'scopeLogs' => [
                        [
                            'scope' => [
                                'name' => 'laravel-posthog-logs',
                            ],
                            'logRecords' => [
                                [
                                    'timeUnixNano' => (string) $timestamp,
                                    'observedTimeUnixNano' => (string) $timestamp,
                                    'severityNumber' => 9,
                                    'severityText' => 'INFO',
                                    'body' => ['stringValue' => $message],
                                    'attributes' => [
                                        ['key' => 'test', 'value' => ['boolValue' => true]],
                                        ['key' => 'log.channel', 'value' => ['stringValue' => 'posthog-test']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->info("Sending test log to: {$endpoint}");
        $this->line("Message: {$message}");
        $this->line('');

        $client = new Client([
            'timeout' => config('posthog-logs.http.timeout', 5),
            'connect_timeout' => config('posthog-logs.http.connect_timeout', 2),
            'verify' => config('posthog-logs.http.verify_ssl', true),
        ]);

        try {
            $response = $client->post($endpoint, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->info("Success! Log sent to PostHog (HTTP {$statusCode})");
                $this->line('');
                $this->line('Check your PostHog dashboard under Logs to see the test entry.');
                $this->line('Filter by service.name="'.$serviceName.'" to find it quickly.');

                return self::SUCCESS;
            }

            $this->error("Unexpected response: HTTP {$statusCode}");
            $this->line($response->getBody()->getContents());

            return self::FAILURE;

        } catch (GuzzleException $e) {
            $this->error('Failed to send test log to PostHog');
            $this->line('');
            $this->line('Error: '.$e->getMessage());

            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $this->line('');
                $this->line('Response body:');
                $this->line($e->getResponse()->getBody()->getContents());
            }

            $this->line('');
            $this->warn('Troubleshooting tips:');
            $this->line('  - Verify your API key is correct (should start with phc_)');
            $this->line('  - Check that the host is correct (us.i.posthog.com or eu.i.posthog.com)');
            $this->line('  - Ensure your server can reach PostHog (check firewall/proxy settings)');

            return self::FAILURE;
        }
    }
}
