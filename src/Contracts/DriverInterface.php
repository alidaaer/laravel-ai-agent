<?php

namespace LaravelAIAgent\Contracts;

use LaravelAIAgent\AgentResponse;

interface DriverInterface
{
    /**
     * Send a prompt to the LLM with optional tools.
     *
     * @param string $message The user message
     * @param array $tools Available tools for the LLM
     * @param array $history Conversation history
     * @param array $options Additional options
     * @return AgentResponse
     */
    public function prompt(
        string $message,
        array $tools = [],
        array $history = [],
        array $options = []
    ): AgentResponse;

    /**
     * Stream a response from the LLM.
     *
     * @param string $message The user message
     * @param array $tools Available tools
     * @param array $history Conversation history
     * @param callable $onChunk Callback for each chunk
     * @return AgentResponse
     */
    public function stream(
        string $message,
        array $tools = [],
        array $history = [],
        callable $onChunk = null
    ): AgentResponse;

    /**
     * Set the model to use.
     */
    public function setModel(string $model): self;

    /**
     * Get the current model.
     */
    public function getModel(): string;

    /**
     * Get token usage for the last request.
     */
    public function getUsage(): array;

    /**
     * Get the driver name.
     */
    public function getName(): string;
}
