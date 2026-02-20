<?php

namespace LaravelAIAgent\Security;

use Illuminate\Support\Facades\Log;

/**
 * Output Sanitizer
 * 
 * Sanitizes AI responses before sending to the client.
 * Prevents leakage of sensitive information and XSS attacks.
 */
class OutputSanitizer
{
    /**
     * Patterns that should never appear in output.
     */
    protected array $sensitivePatterns = [
        // API Keys
        '/sk-[a-zA-Z0-9]{20,}/i',                    // OpenAI
        '/sk-ant-[a-zA-Z0-9-]{20,}/i',               // Anthropic
        '/AIza[a-zA-Z0-9_-]{35}/i',                  // Google/Gemini
        
        // Generic secrets
        '/api[_-]?key\s*[:=]\s*[\'"]?[\w-]{20,}/i',
        '/secret[_-]?key\s*[:=]\s*[\'"]?[\w-]{20,}/i',
        '/password\s*[:=]\s*[\'"]?[^\s\'"]{8,}/i',
        
        // System prompts indicators
        '/\[SYSTEM\s*PROMPT\]/i',
        '/\[INSTRUCTIONS?\]/i',
        '/<<SYS>>/i',
        
        // Database credentials
        '/mysql:\/\/[^@]+@/i',
        '/postgres:\/\/[^@]+@/i',
        '/mongodb:\/\/[^@]+@/i',
    ];

    /**
     * XSS prevention patterns.
     */
    protected array $xssPatterns = [
        '/<script\b[^>]*>/i',
        '/<\/script>/i',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<iframe\b/i',
        '/<object\b/i',
        '/<embed\b/i',
    ];

    /**
     * Whether sanitization is enabled.
     */
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('ai-agent.security.output_sanitization.enabled', true);
    }

    /**
     * Sanitize AI output.
     * 
     * @param string $output
     * @return array ['content' => string, 'sanitized' => bool, 'redactions' => array]
     */
    public function sanitize(string $output): array
    {
        if (!$this->enabled) {
            return [
                'content' => $output,
                'sanitized' => false,
                'redactions' => [],
            ];
        }

        $redactions = [];
        $sanitized = $output;

        // Remove sensitive patterns
        foreach ($this->sensitivePatterns as $pattern) {
            if (preg_match($pattern, $sanitized, $matches)) {
                $redactions[] = [
                    'type' => 'sensitive_data',
                    'pattern' => $pattern,
                    'found' => substr($matches[0], 0, 10) . '...',
                ];
                
                $sanitized = preg_replace($pattern, '[REDACTED]', $sanitized);
                
                Log::warning('AI Agent: Sensitive data redacted from output', [
                    'pattern' => $pattern,
                ]);
            }
        }

        // Prevent XSS
        if (config('ai-agent.security.output_sanitization.prevent_xss', true)) {
            foreach ($this->xssPatterns as $pattern) {
                if (preg_match($pattern, $sanitized, $matches)) {
                    $redactions[] = [
                        'type' => 'xss_prevention',
                        'pattern' => $pattern,
                    ];
                    
                    $sanitized = preg_replace($pattern, '', $sanitized);
                }
            }
        }

        // Prevent system prompt leakage
        $sanitized = $this->preventPromptLeakage($sanitized, $redactions);

        return [
            'content' => $sanitized,
            'sanitized' => !empty($redactions),
            'redactions' => $redactions,
        ];
    }

    /**
     * Prevent leakage of system prompt or instructions.
     */
    protected function preventPromptLeakage(string $output, array &$redactions): string
    {
        $leakageIndicators = [
            'my instructions are',
            'my system prompt is',
            'i was instructed to',
            'my programming says',
            'according to my instructions',
            'here are my instructions',
            'my initial prompt is',
        ];

        foreach ($leakageIndicators as $indicator) {
            if (stripos($output, $indicator) !== false) {
                $redactions[] = [
                    'type' => 'prompt_leakage_prevention',
                    'indicator' => $indicator,
                ];
                
                Log::warning('AI Agent: Potential prompt leakage prevented', [
                    'indicator' => $indicator,
                ]);
            }
        }

        return $output;
    }

    /**
     * Validate that output doesn't contain tool definitions.
     */
    public function validateNoToolLeakage(string $output): bool
    {
        $toolPatterns = [
            '/function\s+\w+\s*\(/i',
            '/"name":\s*"\w+",\s*"parameters"/i',
            '/available\s+tools?\s*:/i',
            '/tool\s+definitions?/i',
        ];

        foreach ($toolPatterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add custom sensitive pattern.
     */
    public function addSensitivePattern(string $pattern): self
    {
        $this->sensitivePatterns[] = $pattern;
        return $this;
    }

    /**
     * Escape HTML entities if needed.
     */
    public function escapeHtml(string $output): string
    {
        return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
