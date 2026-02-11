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
            'content' => $message['content'] ?? '',
            'tool_calls' => !empty($message['tool_calls']) ? json_encode($message['tool_calls']) : null,
            'tool_call_id' => $message['tool_call_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Summarize old messages + enforce hard limit
        try {
            $this->summarizeIfNeeded($conversationId);
        } catch (\Throwable $e) {
            // Never let summarization errors break the chat
        }
        $this->enforceMaxMessages($conversationId);
    }

    public function recall(string $conversationId, int $limit = 50): array
    {
        $recentLimit = config('ai-agent.memory.recent_messages', 4);

        // Use ID ordering (deterministic) instead of created_at (can have duplicate timestamps)
        $messages = DB::table($this->messagesTable)
            ->where('conversation_id', $conversationId)
            ->orderBy('id', 'desc')
            ->limit($recentLimit + 6) // Fetch extra to find a clean boundary
            ->get()
            ->reverse()
            ->values();

        // Convert to array format
        $allMessages = [];
        foreach ($messages as $msg) {
            $message = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];

            if ($msg->tool_calls) {
                $message['tool_calls'] = json_decode($msg->tool_calls, true);
            }

            if ($msg->tool_call_id) {
                $message['tool_call_id'] = $msg->tool_call_id;
            }

            $allMessages[] = $message;
        }

        // Take last N messages, but ensure we start on a clean boundary
        // (not in the middle of a tool sequence)
        $total = count($allMessages);
        $startIndex = max(0, $total - $recentLimit);

        // Walk forward to find a clean start (user or system message)
        while ($startIndex < $total) {
            $role = $allMessages[$startIndex]['role'] ?? '';
            if ($role === 'user' || $role === 'system') {
                break;
            }
            $startIndex++;
        }

        $recentMessages = array_slice($allMessages, $startIndex);

        $history = [];

        // Prepend context summary if exists
        $summary = $this->getContextSummary($conversationId);
        if ($summary) {
            $history[] = [
                'role' => 'system',
                'content' => "[Previous conversation context]\n" . $summary,
            ];
        }

        return array_merge($history, $recentMessages);
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

    public function conversationsWithMeta(): array
    {
        $conversations = DB::table($this->conversationsTable)
            ->orderBy('updated_at', 'desc')
            ->get();

        return $conversations->map(function ($conv) {
            // Get first user message as title
            $firstMessage = DB::table($this->messagesTable)
                ->where('conversation_id', $conv->id)
                ->where('role', 'user')
                ->orderBy('created_at', 'asc')
                ->value('content');

            return [
                'id' => $conv->id,
                'title' => $firstMessage ? mb_substr($firstMessage, 0, 60) : 'New conversation',
                'updated_at' => $conv->updated_at,
            ];
        })->toArray();
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

    /**
     * Summarize old messages without deleting them.
     * Uses a pointer (last_summarized_id) to track progress.
     */
    protected function summarizeIfNeeded(string $conversationId): void
    {
        $summarizeAfter = config('ai-agent.memory.summarize_after', 10);

        // Get the pointer: last message ID that was already summarized
        $lastSummarizedId = $this->getMetadataValue($conversationId, 'last_summarized_id');

        // Get unsummarized messages
        $query = DB::table($this->messagesTable)
            ->where('conversation_id', $conversationId)
            ->orderBy('id', 'asc');

        if ($lastSummarizedId) {
            $query->where('id', '>', $lastSummarizedId);
        }

        $unsummarized = $query->get();

        if ($unsummarized->count() < $summarizeAfter) {
            return;
        }

        // Keep the last few unsummarized, summarize the rest
        $recent = config('ai-agent.memory.recent_messages', 4);
        $toSummarize = $unsummarized->slice(0, $unsummarized->count() - $recent);

        if ($toSummarize->isEmpty()) {
            return;
        }

        $existingSummary = $this->getContextSummary($conversationId);

        // Try AI summarization, fallback to manual
        if (config('ai-agent.memory.ai_summarization', true)) {
            $newSummary = $this->aiSummarize($toSummarize, $existingSummary);
        }

        if (empty($newSummary)) {
            $newSummary = $this->manualSummarize($toSummarize, $existingSummary);
        }

        // Save summary + update pointer (NO deletion)
        $this->saveContextSummary($conversationId, $newSummary);
        $this->saveMetadataValue($conversationId, 'last_summarized_id', $toSummarize->last()->id);
    }

    /**
     * Use the AI driver to generate a high-quality summary.
     */
    protected function aiSummarize($messages, ?string $existingSummary): ?string
    {
        try {
            // Normalize: stdClass (from DB) or array
            $messages = array_map(fn($m) => is_object($m) ? (array) $m : $m, is_array($messages) ? $messages : $messages->toArray());

            $driverName = config('ai-agent.default', 'openai');

            $driverClass = match ($driverName) {
                'openai' => \LaravelAIAgent\Drivers\OpenAIDriver::class,
                'anthropic' => \LaravelAIAgent\Drivers\AnthropicDriver::class,
                'gemini' => \LaravelAIAgent\Drivers\GeminiDriver::class,
                'deepseek' => \LaravelAIAgent\Drivers\DeepSeekDriver::class,
                'openrouter' => \LaravelAIAgent\Drivers\OpenRouterDriver::class,
                default => \LaravelAIAgent\Drivers\OpenAIDriver::class,
            };

            $driver = new $driverClass();

            // Build conversation text for the AI
            $conversationText = '';
            foreach ($messages as $msg) {
                $role = $msg['role'] ?? '';
                $content = $msg['content'] ?? '';
                $toolCalls = $msg['tool_calls'] ?? null;

                if ($role === 'user') {
                    $conversationText .= "User: {$content}\n";
                } elseif ($role === 'assistant') {
                    if ($toolCalls) {
                        $tools = is_string($toolCalls) ? json_decode($toolCalls, true) : $toolCalls;
                        $toolNames = array_column($tools, 'name');
                        $conversationText .= "Assistant: [Called tools: " . implode(', ', $toolNames) . "]\n";
                    } else {
                        $conversationText .= "Assistant: " . mb_substr($content, 0, 100) . "\n";
                    }
                } elseif ($role === 'tool') {
                    $conversationText .= "Tool result: " . mb_substr($content, 0, 80) . "\n";
                }
            }

            $prompt = "Summarize this conversation concisely in the SAME LANGUAGE the user is speaking. Focus on: what the user wanted, what actions were taken, and key outcomes. Keep it under 300 words.";

            if ($existingSummary) {
                $prompt .= "\n\nPrevious summary:\n{$existingSummary}\n\nNew messages to merge:\n{$conversationText}\n\nMerge into one unified summary:";
            } else {
                $prompt .= "\n\nConversation:\n{$conversationText}\n\nSummary:";
            }

            $response = $driver->prompt($prompt, [], [], [
                'max_tokens' => 200,
                'temperature' => 0.3,
            ]);

            $summary = $response->content;

            if (!empty($summary) && mb_strlen($summary) > 10) {
                return mb_substr($summary, 0, 2000);
            }

            return null;
        } catch (\Throwable $e) {
            // Silently fallback to manual summarization
            return null;
        }
    }

    /**
     * Manual fallback: build summary from message text.
     */
    protected function manualSummarize($messages, ?string $existingSummary): string
    {
        // Normalize: stdClass (from DB) or array
        $messages = array_map(fn($m) => is_object($m) ? (array) $m : $m, is_array($messages) ? $messages : $messages->toArray());

        $summaryParts = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            $content = $msg['content'] ?? '';
            $toolCalls = $msg['tool_calls'] ?? null;

            if ($role === 'user') {
                $clean = $this->stripForSummary($content);
                $summaryParts[] = '- User asked: ' . mb_substr($clean, 0, 80);
            } elseif ($role === 'assistant' && $toolCalls) {
                $tools = is_string($toolCalls) ? json_decode($toolCalls, true) : $toolCalls;
                $toolNames = array_column($tools, 'name');
                $summaryParts[] = '- AI called: ' . implode(', ', $toolNames);
            }
        }

        $newSummary = implode("\n", $summaryParts);

        if ($existingSummary) {
            $newSummary = $existingSummary . "\n" . $newSummary;
            if (mb_strlen($newSummary) > 2000) {
                $newSummary = mb_substr($newSummary, -2000);
            }
        }

        return $newSummary;
    }

    /**
     * Hard limit: delete oldest messages when exceeding max_messages.
     */
    protected function enforceMaxMessages(string $conversationId): void
    {
        $max = config('ai-agent.memory.max_messages', 100);

        $count = DB::table($this->messagesTable)
            ->where('conversation_id', $conversationId)
            ->count();

        if ($count <= $max) {
            return;
        }

        $toDelete = $count - $max;

        $idsToDelete = DB::table($this->messagesTable)
            ->where('conversation_id', $conversationId)
            ->orderBy('id', 'asc')
            ->limit($toDelete)
            ->pluck('id');

        DB::table($this->messagesTable)
            ->whereIn('id', $idsToDelete)
            ->delete();
    }

    protected function stripForSummary(string $text): string
    {
        // Remove markdown formatting, emojis, and extra whitespace
        $text = preg_replace('/[*_~`#>\-]+/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    protected function getMetadataValue(string $conversationId, string $key): mixed
    {
        $metadata = DB::table($this->conversationsTable)
            ->where('id', $conversationId)
            ->value('metadata');

        if (!$metadata) {
            return null;
        }

        $data = json_decode($metadata, true);
        return $data[$key] ?? null;
    }

    protected function saveMetadataValue(string $conversationId, string $key, mixed $value): void
    {
        $metadata = DB::table($this->conversationsTable)
            ->where('id', $conversationId)
            ->value('metadata');

        $data = $metadata ? json_decode($metadata, true) : [];
        $data[$key] = $value;

        DB::table($this->conversationsTable)
            ->where('id', $conversationId)
            ->update(['metadata' => json_encode($data)]);
    }

    protected function getContextSummary(string $conversationId): ?string
    {
        return $this->getMetadataValue($conversationId, 'context_summary');
    }

    protected function saveContextSummary(string $conversationId, string $summary): void
    {
        $this->saveMetadataValue($conversationId, 'context_summary', $summary);
    }
}
