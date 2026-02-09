<?php

namespace LaravelAIAgent\Contracts;

interface MemoryInterface
{
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
}
