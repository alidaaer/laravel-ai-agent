<?php

namespace LaravelAIAgent\Memory;

use Illuminate\Support\Facades\Session;
use LaravelAIAgent\Contracts\MemoryInterface;

class SessionMemory implements MemoryInterface
{
    protected string $prefix = 'ai_agent_memory_';

    public function remember(string $conversationId, array $message): void
    {
        $key = $this->prefix . $conversationId;
        $messages = Session::get($key, []);
        
        $messages[] = $message;

        // Limit to max messages
        $max = config('ai-agent.memory.max_messages', 50);
        if (count($messages) > $max) {
            $messages = array_slice($messages, -$max);
        }

        Session::put($key, $messages);
        Session::save(); // Force save session
    }

    public function recall(string $conversationId, int $limit = 50): array
    {
        $key = $this->prefix . $conversationId;
        $messages = Session::get($key, []);
        
        \Illuminate\Support\Facades\Log::debug('SessionMemory recall', [
            'conversationId' => $conversationId,
            'key' => $key,
            'messages_count' => count($messages),
        ]);

        return array_slice($messages, -$limit);
    }

    public function forget(string $conversationId): void
    {
        $key = $this->prefix . $conversationId;
        Session::forget($key);
        Session::save();
    }

    public function conversations(): array
    {
        $all = Session::all();
        $conversations = [];

        foreach ($all as $key => $value) {
            if (str_starts_with($key, $this->prefix)) {
                $conversations[] = substr($key, strlen($this->prefix));
            }
        }

        return $conversations;
    }
}
