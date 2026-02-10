<?php

namespace LaravelAIAgent\Security;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use LaravelAIAgent\Events\ToolCalled;
use LaravelAIAgent\Events\ToolExecuted;
use LaravelAIAgent\Events\ToolFailed;
use LaravelAIAgent\Events\SecurityViolation;

/**
 * Audit Logger
 * 
 * Logs all AI agent activities for security and debugging.
 */
class AuditLogger
{
    /**
     * Log channel.
     */
    protected string $channel;

    /**
     * Whether DB logging is enabled.
     */
    protected bool $dbLogging;

    /**
     * Table name for DB logging.
     */
    protected string $tableName = 'agent_logs';

    public function __construct()
    {
        $this->channel = config('ai-agent.security.audit.channel', 'ai-agent');
        $this->dbLogging = false;
    }

    /**
     * Log a tool call.
     */
    public function logToolCall(
        string $toolName,
        array $arguments,
        ?string $userId = null,
        ?string $conversationId = null
    ): void {
        $data = [
            'action' => 'tool_call',
            'tool' => $toolName,
            'arguments' => $this->sanitizeArguments($arguments),
            'user_id' => $userId ?? auth()->id(),
            'conversation_id' => $conversationId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ];

        $this->log('info', 'Tool called: ' . $toolName, $data);
        $this->logToDatabase($data);
    }

    /**
     * Log a tool execution result.
     */
    public function logToolExecution(
        string $toolName,
        array $arguments,
        mixed $result,
        float $executionTime,
        ?string $userId = null
    ): void {
        $data = [
            'action' => 'tool_executed',
            'tool' => $toolName,
            'arguments' => $this->sanitizeArguments($arguments),
            'result_summary' => $this->summarizeResult($result),
            'execution_time_ms' => round($executionTime * 1000, 2),
            'user_id' => $userId ?? auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ];

        $this->log('info', 'Tool executed: ' . $toolName, $data);
        $this->logToDatabase($data);
    }

    /**
     * Log a tool failure.
     */
    public function logToolFailure(
        string $toolName,
        array $arguments,
        \Throwable $exception,
        ?string $userId = null
    ): void {
        $data = [
            'action' => 'tool_failed',
            'tool' => $toolName,
            'arguments' => $this->sanitizeArguments($arguments),
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'error_trace' => $exception->getTraceAsString(),
            'user_id' => $userId ?? auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ];

        $this->log('error', 'Tool failed: ' . $toolName, $data);
        $this->logToDatabase($data);
    }

    /**
     * Log a security violation.
     */
    public function logSecurityViolation(
        string $type,
        array $details,
        ?string $userId = null
    ): void {
        $data = [
            'action' => 'security_violation',
            'violation_type' => $type,
            'details' => $details,
            'user_id' => $userId ?? auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ];

        $this->log('warning', 'Security violation: ' . $type, $data);
        $this->logToDatabase($data);
    }

    /**
     * Log a chat message.
     */
    public function logChat(
        string $direction, // 'in' or 'out'
        string $message,
        ?string $conversationId = null,
        ?string $userId = null,
        ?array $metadata = null
    ): void {
        $data = [
            'action' => 'chat_' . $direction,
            'message_length' => strlen($message),
            'message_preview' => substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
            'conversation_id' => $conversationId,
            'user_id' => $userId ?? auth()->id(),
            'metadata' => $metadata,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->log('info', 'Chat ' . $direction . ': ' . substr($message, 0, 50), $data);
        
        if (config('ai-agent.security.audit.log_messages', false)) {
            $this->logToDatabase($data);
        }
    }

    /**
     * Sanitize arguments to remove sensitive data.
     */
    protected function sanitizeArguments(array $arguments): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'api_key', 'key'];
        
        return collect($arguments)->map(function ($value, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    return '[REDACTED]';
                }
            }
            return $value;
        })->toArray();
    }

    /**
     * Summarize result for logging.
     */
    protected function summarizeResult(mixed $result): string
    {
        if (is_array($result)) {
            $summary = [
                'type' => 'array',
                'keys' => array_keys($result),
                'size' => count($result),
            ];
            
            if (isset($result['success'])) {
                $summary['success'] = $result['success'];
            }
            
            return json_encode($summary, JSON_UNESCAPED_UNICODE);
        }

        if (is_string($result)) {
            return 'string(' . strlen($result) . ')';
        }

        return gettype($result);
    }

    /**
     * Write to log.
     */
    protected function log(string $level, string $message, array $context): void
    {
        if (!config('ai-agent.security.audit.enabled', true)) {
            return;
        }

        Log::channel($this->channel)->$level('[AI-Agent] ' . $message, $context);
    }

    /**
     * Log to database.
     */
    protected function logToDatabase(array $data): void
    {
        if (!$this->dbLogging) {
            return;
        }

        try {
            DB::table($this->tableName)->insert([
                'type' => $data['action'] ?? 'unknown',
                'name' => $data['tool'] ?? $data['violation_type'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'user_id' => $data['user_id'] ?? null,
                'ip_address' => $data['ip_address'] ?? request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Agent: Failed to log to database', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register event listeners for automatic logging.
     */
    public static function registerListeners(): void
    {
        $logger = new static();

        \Illuminate\Support\Facades\Event::listen(ToolCalled::class, function ($event) use ($logger) {
            $logger->logToolCall(
                $event->tool['name'] ?? 'unknown',
                $event->tool['arguments'] ?? []
            );
        });

        \Illuminate\Support\Facades\Event::listen(ToolExecuted::class, function ($event) use ($logger) {
            $logger->logToolExecution(
                $event->tool['name'] ?? 'unknown',
                $event->tool['arguments'] ?? [],
                $event->result,
                $event->executionTime ?? 0
            );
        });

        \Illuminate\Support\Facades\Event::listen(ToolFailed::class, function ($event) use ($logger) {
            $logger->logToolFailure(
                $event->tool['name'] ?? 'unknown',
                $event->tool['arguments'] ?? [],
                $event->exception
            );
        });

        \Illuminate\Support\Facades\Event::listen(SecurityViolation::class, function ($event) use ($logger) {
            $logger->logSecurityViolation(
                $event->type,
                $event->details,
                $event->userId
            );
        });
    }
}
