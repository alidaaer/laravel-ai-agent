<?php

namespace LaravelAIAgent;

class AgentContext
{
    protected bool $isAICall = false;
    protected ?string $currentTool = null;
    protected ?string $conversationId = null;

    /**
     * Mark that we are currently inside a tool execution.
     */
    public function enterToolCall(string $toolName, ?string $conversationId = null): void
    {
        $this->isAICall = true;
        $this->currentTool = $toolName;
        $this->conversationId = $conversationId;
    }

    /**
     * Mark that the tool execution is done.
     */
    public function exitToolCall(): void
    {
        $this->isAICall = false;
        $this->currentTool = null;
    }

    /**
     * Check if we are currently inside an AI tool execution.
     */
    public function isAICall(): bool
    {
        return $this->isAICall;
    }

    /**
     * Get the name of the current tool being executed.
     */
    public function currentTool(): ?string
    {
        return $this->currentTool;
    }

    /**
     * Get the conversation ID.
     */
    public function conversationId(): ?string
    {
        return $this->conversationId;
    }
}
