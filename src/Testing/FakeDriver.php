<?php

namespace LaravelAIAgent\Testing;

use LaravelAIAgent\AgentResponse;
use LaravelAIAgent\Contracts\DriverInterface;

class FakeDriver implements DriverInterface
{
    protected array $responses = [];
    protected int $currentIndex = 0;
    protected array $calledTools = [];
    protected array $prompts = [];
    protected string $model = 'fake-model';

    /**
     * Set the fake responses.
     */
    public function setResponses(array $responses): self
    {
        $this->responses = $responses;
        $this->currentIndex = 0;
        return $this;
    }

    /**
     * Add a single response.
     */
    public function addResponse(string|array $response): self
    {
        if (is_string($response)) {
            $this->responses[] = ['content' => $response, 'tool_calls' => []];
        } else {
            $this->responses[] = $response;
        }
        return $this;
    }

    public function prompt(
        string $message,
        array $tools = [],
        array $history = [],
        array $options = []
    ): AgentResponse {
        $this->prompts[] = [
            'message' => $message,
            'tools' => $tools,
            'history' => $history,
            'options' => $options,
        ];

        $response = $this->responses[$this->currentIndex] ?? $this->responses[0] ?? [
            'content' => 'Fake response',
            'tool_calls' => [],
        ];

        $this->currentIndex++;

        // Track tool calls
        if (!empty($response['tool_calls'])) {
            $this->calledTools = array_merge($this->calledTools, $response['tool_calls']);
        }

        return new AgentResponse(
            content: $response['content'] ?? '',
            toolCalls: $response['tool_calls'] ?? [],
            usage: $response['usage'] ?? ['prompt_tokens' => 100, 'completion_tokens' => 50],
            finishReason: $response['finish_reason'] ?? 'stop',
        );
    }

    public function stream(
        string $message,
        array $tools = [],
        array $history = [],
        callable $onChunk = null
    ): AgentResponse {
        $response = $this->prompt($message, $tools, $history, []);

        if ($onChunk) {
            foreach (str_split($response->content, 10) as $chunk) {
                $onChunk($chunk);
            }
        }

        return $response;
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
        return ['prompt_tokens' => 100, 'completion_tokens' => 50];
    }

    public function getName(): string
    {
        return 'fake';
    }

    // Assertion methods

    /**
     * Assert a tool was called.
     */
    public function assertToolCalled(string $name, ?array $arguments = null): void
    {
        $found = collect($this->calledTools)->first(fn($t) => $t['name'] === $name);

        if (!$found) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Tool '{$name}' was not called. Called tools: " . 
                implode(', ', array_column($this->calledTools, 'name'))
            );
        }

        if ($arguments !== null) {
            \PHPUnit\Framework\Assert::assertEquals(
                $arguments,
                $found['arguments'],
                "Tool '{$name}' was called with different arguments"
            );
        }
    }

    /**
     * Assert a tool was not called.
     */
    public function assertToolNotCalled(string $name): void
    {
        $found = collect($this->calledTools)->first(fn($t) => $t['name'] === $name);

        if ($found) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Tool '{$name}' was called but should not have been"
            );
        }
    }

    /**
     * Assert no tools were called.
     */
    public function assertNoToolsCalled(): void
    {
        if (!empty($this->calledTools)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected no tools to be called, but these were: " . 
                implode(', ', array_column($this->calledTools, 'name'))
            );
        }
    }

    /**
     * Get all prompts that were sent.
     */
    public function getPrompts(): array
    {
        return $this->prompts;
    }

    /**
     * Get all tool calls.
     */
    public function getToolCalls(): array
    {
        return $this->calledTools;
    }

    /**
     * Reset the fake driver.
     */
    public function reset(): void
    {
        $this->responses = [];
        $this->currentIndex = 0;
        $this->calledTools = [];
        $this->prompts = [];
    }
}
