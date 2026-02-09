<?php

namespace LaravelAIAgent;

class AgentResponse
{
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly array $usage = [],
        public readonly ?string $finishReason = null,
    ) {}

    /**
     * Check if the response has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    /**
     * Get the response as a string.
     */
    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Get the response as an array.
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
            'usage' => $this->usage,
            'finish_reason' => $this->finishReason,
        ];
    }

    /**
     * Get token count.
     */
    public function tokens(): int
    {
        return ($this->usage['prompt_tokens'] ?? 0) + ($this->usage['completion_tokens'] ?? 0);
    }
}
