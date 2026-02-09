<?php

namespace LaravelAIAgent\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Security Violation Event
 * 
 * Fired when a security violation is detected.
 */
class SecurityViolation
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $type,
        public array $details = [],
        public ?string $userId = null,
        public ?string $ipAddress = null
    ) {
        $this->userId = $userId ?? auth()->id();
        $this->ipAddress = $ipAddress ?? request()->ip();
    }

    /**
     * Get violation summary.
     */
    public function getSummary(): string
    {
        return sprintf(
            '[%s] Security Violation: %s | User: %s | IP: %s | Details: %s',
            now()->toIso8601String(),
            $this->type,
            $this->userId ?? 'guest',
            $this->ipAddress ?? 'unknown',
            json_encode($this->details, JSON_UNESCAPED_UNICODE)
        );
    }
}
