<?php

namespace LaravelAIAgent\Memory;

use LaravelAIAgent\Contracts\MemoryInterface;

class NullMemory implements MemoryInterface
{
    public function remember(string $conversationId, array $message): void
    {
        // Do nothing - no memory
    }

    public function recall(string $conversationId, int $limit = 50): array
    {
        return [];
    }

    public function forget(string $conversationId): void
    {
        // Do nothing
    }

    public function conversations(): array
    {
        return [];
    }
}
