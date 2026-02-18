<?php

namespace LaravelAIAgent\Memory;

use Illuminate\Support\Facades\Session;
use LaravelAIAgent\Contracts\MemoryInterface;

class SessionMemory implements MemoryInterface
{
    protected string $prefix = 'ai_agent_memory_';
    protected string $metaPrefix = 'ai_agent_meta_';
    protected ?string $agentName = null;

    public function forAgent(string $agentName): static
    {
        $this->agentName = $agentName;
        return $this;
    }

    public function remember(string $conversationId, array $message): void
    {
        $key = $this->prefix . $conversationId;
        $messages = Session::get($key, []);
        
        $messages[] = $message;

        // Phase 1: Summarize without deleting (uses index pointer)
        $this->summarizeIfNeeded($conversationId, $messages);

        // Phase 2: Hard limit — delete oldest when exceeding max_messages
        $max = config('ai-agent.memory.max_messages', 100);
        if (count($messages) > $max) {
            $toRemove = count($messages) - $max;
            $messages = array_slice($messages, $toRemove);

            // Adjust pointer since indices shifted
            $metaKey = $this->metaPrefix . $conversationId;
            $meta = Session::get($metaKey, []);
            if (isset($meta['last_summarized_index'])) {
                $meta['last_summarized_index'] = max(-1, $meta['last_summarized_index'] - $toRemove);
                Session::put($metaKey, $meta);
            }
        }

        Session::put($key, $messages);

        // Update conversation metadata
        $this->updateMeta($conversationId, $message);
    }

    public function recall(string $conversationId, int $limit = 50): array
    {
        $key = $this->prefix . $conversationId;
        $messages = Session::get($key, []);
        
        // For history endpoint (limit >= 50), return all messages
        if ($limit >= 50) {
            return $messages;
        }
        
        // For LLM, apply recent limit logic
        $recent = config('ai-agent.memory.recent_messages', 4);
        
        // Take last N messages, but ensure we start on a clean boundary
        $total = count($messages);
        $startIndex = max(0, $total - $recent);

        // Walk forward to find a clean start (user or system message)
        while ($startIndex < $total) {
            $role = $messages[$startIndex]['role'] ?? '';
            if ($role === 'user' || $role === 'system') {
                break;
            }
            $startIndex++;
        }

        $recentMessages = array_slice($messages, $startIndex);

        $history = [];

        // Prepend context summary if exists
        $metaKey = $this->metaPrefix . $conversationId;
        $meta = Session::get($metaKey, []);
        $summary = $meta['context_summary'] ?? null;
        if ($summary) {
            $history[] = [
                'role' => 'system',
                'content' => "[Previous conversation context]\n" . $summary,
            ];
        }

        return array_merge($history, $recentMessages);
    }

    public function recallForLLM(string $conversationId): array
    {
        // For session memory, use recall with small limit to trigger LLM logic
        return $this->recall($conversationId, 10);
    }

    public function forget(string $conversationId): void
    {
        Session::forget($this->prefix . $conversationId);
        Session::forget($this->metaPrefix . $conversationId);
    }

    public function conversations(): array
    {
        $all = Session::all();
        $conversations = [];

        foreach ($all as $key => $value) {
            if (str_starts_with($key, $this->prefix)) {
                $id = substr($key, strlen($this->prefix));

                // Filter by agent if scoped
                if ($this->agentName) {
                    $meta = Session::get($this->metaPrefix . $id, []);
                    if (($meta['agent_name'] ?? null) !== $this->agentName) {
                        continue;
                    }
                }

                $conversations[] = $id;
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
                $meta = is_array($value) ? $value : [];

                // Filter by agent if scoped
                if ($this->agentName && ($meta['agent_name'] ?? null) !== $this->agentName) {
                    continue;
                }

                $metaIds[$id] = true;
                $conversations[] = [
                    'id' => $id,
                    'title' => $meta['title'] ?? 'New conversation',
                    'updated_at' => $meta['updated_at'] ?? now()->toISOString(),
                ];
            }
        }

        // Backward compat: include memory keys without meta (older conversations)
        // Only include unscoped conversations when no agent filter is set
        if (!$this->agentName) {
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
        }

        // Sort by updated_at descending (newest first)
        usort($conversations, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        return $conversations;
    }

    /**
     * Summarize old messages without deleting them.
     * Uses an index pointer to track what's been summarized.
     */
    protected function summarizeIfNeeded(string $conversationId, array $messages): void
    {
        $summarizeAfter = config('ai-agent.memory.summarize_after', 10);
        $recent = config('ai-agent.memory.recent_messages', 4);

        $metaKey = $this->metaPrefix . $conversationId;
        $meta = Session::get($metaKey, []);

        // Pointer: index of last summarized message
        $lastSummarizedIndex = $meta['last_summarized_index'] ?? -1;

        // Count unsummarized messages
        $unsummarizedCount = count($messages) - ($lastSummarizedIndex + 1);

        if ($unsummarizedCount < $summarizeAfter) {
            return;
        }

        // Summarize from pointer+1 up to (total - recent)
        $endIndex = count($messages) - $recent;
        if ($endIndex <= $lastSummarizedIndex + 1) {
            return;
        }

        $toSummarize = array_slice($messages, $lastSummarizedIndex + 1, $endIndex - ($lastSummarizedIndex + 1));
        $existingSummary = $meta['context_summary'] ?? null;

        // Try AI summarization, fallback to manual
        $summary = null;
        if (config('ai-agent.memory.ai_summarization', true)) {
            $summary = $this->aiSummarize($toSummarize, $existingSummary);
        }

        if (empty($summary)) {
            $summary = $this->manualSummarize($toSummarize, $existingSummary);
        }

        $meta['context_summary'] = $summary;
        $meta['last_summarized_index'] = $endIndex - 1;
        Session::put($metaKey, $meta);
    }

    /**
     * Use the AI driver to generate a high-quality summary.
     */
    protected function aiSummarize(array $messages, ?string $existingSummary): ?string
    {
        try {
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

            $conversationText = '';
            foreach ($messages as $msg) {
                $role = $msg['role'] ?? '';
                $content = $msg['content'] ?? '';
                $toolCalls = $msg['tool_calls'] ?? null;

                if ($role === 'user') {
                    $conversationText .= "User: {$content}\n";
                } elseif ($role === 'assistant') {
                    if ($toolCalls) {
                        $toolNames = array_column($toolCalls, 'name');
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
            return null;
        }
    }

    /**
     * Manual fallback: build summary from message text.
     */
    protected function manualSummarize(array $messages, ?string $existingSummary): string
    {
        $parts = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            if ($role === 'user') {
                $clean = $this->stripForSummary($msg['content'] ?? '');
                $parts[] = '- User asked: ' . mb_substr($clean, 0, 80);
            } elseif ($role === 'assistant' && !empty($msg['tool_calls'])) {
                $toolNames = array_column($msg['tool_calls'], 'name');
                $parts[] = '- AI called: ' . implode(', ', $toolNames);
            }
        }

        $newSummary = implode("\n", $parts);

        if ($existingSummary) {
            $newSummary = $existingSummary . "\n" . $newSummary;
            if (mb_strlen($newSummary) > 2000) {
                $newSummary = mb_substr($newSummary, -2000);
            }
        }

        return $newSummary;
    }

    protected function stripForSummary(string $text): string
    {
        $text = preg_replace('/[*_~`#>\-]+/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    protected function updateMeta(string $conversationId, array $message): void
    {
        $metaKey = $this->metaPrefix . $conversationId;
        $meta = Session::get($metaKey, []);

        // Set title from first user message
        if (empty($meta['title']) && $message['role'] === 'user') {
            $meta['title'] = mb_substr($message['content'], 0, 60);
        }

        // Store agent name for scoping
        if ($this->agentName && !isset($meta['agent_name'])) {
            $meta['agent_name'] = $this->agentName;
        }

        $meta['updated_at'] = now()->toISOString();

        Session::put($metaKey, $meta);
    }
}
