<?php

namespace LaravelAIAgent\Memory;

use Illuminate\Support\Facades\DB;
use LaravelAIAgent\Contracts\MemoryInterface;

class DatabaseMemory implements MemoryInterface
{
    protected string $conversationsTable = 'agent_conversations';
    protected string $messagesTable = 'agent_messages';

    public function remember(string $conversationId, array $message): void
    {
        // Ensure conversation exists
        $this->ensureConversationExists($conversationId);

        // Insert message
        DB::table($this->messagesTable)->insert([
            'conversation_id' => $conversationId,
            'role' => $message['role'],
            'content' => $message['content'],
            'tool_calls' => isset($message['tool_calls']) ? json_encode($message['tool_calls']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Limit messages
        $this->limitMessages($conversationId);
    }

    public function recall(string $conversationId, int $limit = 50): array
    {
        $messages = DB::table($this->messagesTable)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        return $messages->map(function ($msg) {
            $message = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];

            if ($msg->tool_calls) {
                $message['tool_calls'] = json_decode($msg->tool_calls, true);
            }

            return $message;
        })->toArray();
    }

    public function forget(string $conversationId): void
    {
        DB::table($this->messagesTable)
            ->where('conversation_id', $conversationId)
            ->delete();

        DB::table($this->conversationsTable)
            ->where('id', $conversationId)
            ->delete();
    }

    public function conversations(): array
    {
        return DB::table($this->conversationsTable)
            ->orderBy('updated_at', 'desc')
            ->pluck('id')
            ->toArray();
    }

    protected function ensureConversationExists(string $conversationId): void
    {
        $exists = DB::table($this->conversationsTable)
            ->where('id', $conversationId)
            ->exists();

        if (!$exists) {
            DB::table($this->conversationsTable)->insert([
                'id' => $conversationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table($this->conversationsTable)
                ->where('id', $conversationId)
                ->update(['updated_at' => now()]);
        }
    }

    protected function limitMessages(string $conversationId): void
    {
        $max = config('ai-agent.memory.max_messages', 50);

        $count = DB::table($this->messagesTable)
            ->where('conversation_id', $conversationId)
            ->count();

        if ($count > $max) {
            $toDelete = $count - $max;
            
            $idsToDelete = DB::table($this->messagesTable)
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'asc')
                ->limit($toDelete)
                ->pluck('id');

            DB::table($this->messagesTable)
                ->whereIn('id', $idsToDelete)
                ->delete();
        }
    }
}
