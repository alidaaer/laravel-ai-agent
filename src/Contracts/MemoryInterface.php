<?php

namespace LaravelAIAgent\Contracts;

interface MemoryInterface
{
    /**
     * Scope this memory instance to a specific agent.
     * When set, conversations are isolated per agent.
     */
    public function forAgent(string $agentName): static;

    /**
     * Store a message in the conversation history.
     *
     * @param string $conversationId
     * @param array $message ['role' => 'user|assistant|system|tool', 'content' => '...']
     */
    public function remember(string $conversationId, array $message): void;

    /**
     * Retrieve the conversation history.
     *
     * @param string $conversationId
     * @param int $limit Maximum messages to retrieve
     * @return array
     */
    public function recall(string $conversationId, int $limit = 50): array;

    /**
     * Retrieve recent messages for LLM context with clean boundary logic.
     *
     * @param string $conversationId
     * @return array
     */
    public function recallForLLM(string $conversationId): array;

    /**
     * Clear the conversation history.
     *
     * @param string $conversationId
     */
    public function forget(string $conversationId): void;

    /**
     * Get all conversation IDs.
     *
     * @return array
     */
    public function conversations(): array;

    /**
     * Get all conversations with metadata.
     *
     * @return array [['id' => '...', 'title' => '...', 'updated_at' => '...']]
     */
    public function conversationsWithMeta(): array;
}
