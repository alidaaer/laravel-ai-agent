<?php

namespace LaravelAIAgent\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaravelAIAgent\AgentResponse;
use LaravelAIAgent\Contracts\DriverInterface;
use LaravelAIAgent\Exceptions\DriverException;

abstract class AbstractDriver implements DriverInterface
{
    protected string $model;
    protected int $timeout = 60;
    protected int $maxRetries = 3;
    protected int $retryDelay = 1000;
    protected array $lastUsage = [];

    /**
     * Make an HTTP request with retry logic.
     */
    protected function request(string $url, array $payload, array $headers = []): array
    {
        $attempts = 0;
        $lastException = null;
        $lastError = null;

        while ($attempts < $this->maxRetries) {
            try {
                $http = Http::timeout($this->timeout)
                    ->withHeaders($headers);
                
                // Disable SSL verification if configured (default: false = verify SSL)
                if (config('ai-agent.verify_ssl', false) === false) {
                    $http = $http->withoutVerifying();
                }
                
                $response = $http->post($url, $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                // Log the full error for debugging
                $lastError = $response->body();
                Log::error("AI Agent API Error", [
                    'status' => $response->status(),
                    'body' => $lastError,
                    'url' => preg_replace('/key=[^&]+/', 'key=***', $url),
                ]);

                // Rate limited - wait and retry
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? ($this->retryDelay / 1000));
                    Log::warning("AI Agent: Rate limited, retrying in {$retryAfter}s");
                    
                    // If we've exhausted retries, throw a user-friendly error
                    if ($attempts >= $this->maxRetries - 1) {
                        throw new DriverException(
                            "Rate limit exceeded. You have reached the maximum number of requests. Please try again later.",
                            ['error' => 'rate_limit_exceeded'],
                            429
                        );
                    }
                    
                    sleep($retryAfter);
                    $attempts++;
                    continue;
                }

                // Server error - retry with backoff
                if ($response->status() >= 500) {
                    Log::warning("AI Agent: Server error {$response->status()}, retrying...");
                    usleep($this->retryDelay * 1000 * ($attempts + 1));
                    $attempts++;
                    continue;
                }

                // Client error - don't retry
                throw new DriverException(
                    "API error: " . ($response->json()['error']['message'] ?? $response->body()),
                    $response->json(),
                    $response->status()
                );

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                $attempts++;
                Log::warning("AI Agent: Connection error, attempt {$attempts}/{$this->maxRetries}");
                usleep($this->retryDelay * 1000 * $attempts);
            }
        }

        throw new DriverException(
            "Failed after {$this->maxRetries} attempts: " . ($lastException?->getMessage() ?? $lastError ?? 'Unknown error')
        );
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getUsage(): array
    {
        return $this->lastUsage;
    }

    /**
     * Convert tools to the format expected by the driver.
     */
    abstract protected function formatTools(array $tools): array;

    /**
     * Parse the response from the driver.
     */
    abstract protected function parseResponse(array $response): AgentResponse;
}
