<?php

namespace LaravelAIAgent\Memory;

use Illuminate\Support\Facades\Session;
use LaravelAIAgent\Contracts\MemoryInterface;

class SessionMemory implements MemoryInterface
{
    protected string $prefix = 'ai_agent_memory_';
    protected string $metaPrefix = 'ai_agent_meta_';

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

        // Update conversation metadata
        $this->updateMeta($conversationId, $message);

        Session::save();
    }

    public function recall(string $conversationId, int $limit = 50): array
    {
        $key = $this->prefix . $conversationId;
        $messages = Session::get($key, []);

        return array_slice($messages, -$limit);
    }

    public function forget(string $conversationId): void
    {
        Session::forget($this->prefix . $conversationId);
        Session::forget($this->metaPrefix . $conversationId);
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

    public function conversationsWithMeta(): array
    {
        $all = Session::all();
        $conversations = [];
        $metaIds = [];

        // Collect conversations that have metadata
        foreach ($all as $key => $value) {
            if (str_starts_with($key, $this->metaPrefix)) {
                $id = substr($key, strlen($this->metaPrefix));
                $metaIds[$id] = true;
                $meta = is_array($value) ? $value : [];
                $conversations[] = [
                    'id' => $id,
                    'title' => $meta['title'] ?? 'New conversation',
                    'updated_at' => $meta['updated_at'] ?? now()->toISOString(),
                ];
            }
        }

        // Backward compat: include memory keys without meta (older conversations)
        foreach ($all as $key => $value) {
            if (str_starts_with($key, $this->prefix)) {
                $id = substr($key, strlen($this->prefix));
                if (!isset($metaIds[$id]) && is_array($value) && !empty($value)) {
                    $firstUserMsg = collect($value)->firstWhere('role', 'user');
                    $conversations[] = [
                        'id' => $id,
                        'title' => $firstUserMsg ? mb_substr($firstUserMsg['content'], 0, 60) : 'New conversation',
                        'updated_at' => now()->toISOString(),
                    ];
                }
            }
        }

        // Sort by updated_at descending (newest first)
        usort($conversations, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        return $conversations;
    }

    protected function updateMeta(string $conversationId, array $message): void
    {
        $metaKey = $this->metaPrefix . $conversationId;
        $meta = Session::get($metaKey, []);

        // Set title from first user message
        if (empty($meta['title']) && $message['role'] === 'user') {
            $meta['title'] = mb_substr($message['content'], 0, 60);
        }

        $meta['updated_at'] = now()->toISOString();

        Session::put($metaKey, $meta);
    }
}
