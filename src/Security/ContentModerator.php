<?php

namespace LaravelAIAgent\Security;

use Illuminate\Support\Facades\Log;

/**
 * Content Moderation Layer
 * 
 * Filters and validates user input before sending to AI.
 * Detects potential prompt injection attacks and malicious patterns.
 */
class ContentModerator
{
    /**
     * Known prompt injection patterns.
     */
    protected array $injectionPatterns = [
        // Direct instruction override
        '/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions?|prompts?|rules?)/i',
        '/forget\s+(all\s+)?(previous|prior|your)\s+(instructions?|prompts?|rules?)/i',
        '/disregard\s+(all\s+)?(previous|prior|your)\s+(instructions?|prompts?|rules?)/i',
        
        // Role manipulation
        '/you\s+are\s+now\s+(a|an)\s+/i',
        '/pretend\s+(to\s+be|you\s*\'?re)\s+/i',
        '/act\s+as\s+(a|an|if)\s+/i',
        '/role\s*-?\s*play\s+as/i',
        
        // Jailbreak attempts
        '/\bDAN\b.*do\s+anything\s+now/i',
        '/developer\s+mode/i',
        '/jailbreak/i',
        '/bypass\s+(the\s+)?(restrictions?|filters?|safety)/i',
        
        // System prompt extraction
        '/what\s+(is|are)\s+your\s+(system\s+)?prompt/i',
        '/show\s+(me\s+)?your\s+(system\s+)?instructions?/i',
        '/reveal\s+your\s+(system\s+)?prompt/i',
        '/print\s+your\s+(initial\s+)?instructions?/i',
        
        // API key extraction
        '/what\s+(is|are)\s+your\s+api\s*key/i',
        '/show\s+(me\s+)?your\s+(secret|api)\s*key/i',
        '/reveal\s+(your\s+)?(credentials?|secrets?|keys?)/i',
    ];

    /**
     * Dangerous action keywords.
     */
    protected array $dangerousKeywords = [
        'drop table', 'truncate table', 'delete all', 'remove all',
        'destroy', 'wipe', 'clear all', 'reset all',
    ];

    /**
     * Maximum allowed message length.
     */
    protected int $maxMessageLength;

    /**
     * Whether moderation is enabled.
     */
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('ai-agent.security.content_moderation.enabled', true);
        $this->maxMessageLength = config('ai-agent.security.max_message_length', 5000);
    }

    /**
     * Moderate user input.
     * 
     * @param string $message
     * @return array ['allowed' => bool, 'reason' => string|null, 'sanitized' => string]
     */
    public function moderate(string $message): array
    {
        if (!$this->enabled) {
            return [
                'allowed' => true,
                'reason' => null,
                'sanitized' => $message,
                'warnings' => [],
            ];
        }

        $warnings = [];
        $sanitized = $message;

        // Check message length
        if (strlen($message) > $this->maxMessageLength) {
            return [
                'allowed' => false,
                'reason' => 'Message exceeds maximum length of ' . $this->maxMessageLength . ' characters',
                'sanitized' => $message,
                'warnings' => ['message_too_long'],
            ];
        }

        // Check for prompt injection patterns
        foreach ($this->injectionPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $warnings[] = 'prompt_injection_detected';
                
                if (config('ai-agent.security.content_moderation.block_injections', true)) {
                    Log::warning('AI Agent: Prompt injection attempt blocked', [
                        'pattern' => $pattern,
                        'message' => substr($message, 0, 200),
                    ]);
                    
                    return [
                        'allowed' => false,
                        'reason' => 'Message contains potentially harmful content',
                        'sanitized' => $message,
                        'warnings' => $warnings,
                    ];
                }
            }
        }

        // Check for dangerous keywords (warn but allow)
        foreach ($this->dangerousKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                $warnings[] = 'dangerous_keyword: ' . $keyword;
            }
        }

        // Log warnings
        if (!empty($warnings)) {
            Log::info('AI Agent: Content moderation warnings', [
                'warnings' => $warnings,
                'message' => substr($message, 0, 200),
            ]);
        }

        return [
            'allowed' => true,
            'reason' => null,
            'sanitized' => $sanitized,
            'warnings' => $warnings,
        ];
    }

    /**
     * Add custom injection pattern.
     */
    public function addPattern(string $pattern): self
    {
        $this->injectionPatterns[] = $pattern;
        return $this;
    }

    /**
     * Add custom dangerous keyword.
     */
    public function addDangerousKeyword(string $keyword): self
    {
        $this->dangerousKeywords[] = strtolower($keyword);
        return $this;
    }

    /**
     * Check if message requires confirmation.
     */
    public function requiresConfirmation(string $message): bool
    {
        $confirmPatterns = [
            '/delete/i', '/remove/i', '/cancel/i', '/حذف/u', '/إلغاء/u',
            '/drop/i', '/truncate/i', '/destroy/i', '/wipe/i',
        ];

        foreach ($confirmPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }
}
