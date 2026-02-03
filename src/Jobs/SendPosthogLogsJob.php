<?php

namespace RobbieKibler\PosthogLogs\Jobs;

use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPosthogLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $apiKey,
        public readonly array $payload,
        public readonly int $timeout = 5,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = new Client([
            'timeout' => $this->timeout,
            'connect_timeout' => max(1, (int) ($this->timeout / 2)),
            'verify' => true,
        ]);

        $client->post($this->endpoint, [
            'json' => $this->payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->apiKey}",
            ],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        error_log("[PosthogLogs] Job failed after retries: {$e->getMessage()}");
    }
}
