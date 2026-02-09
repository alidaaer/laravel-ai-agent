<?php

namespace LaravelAIAgent\Events;

class AgentStarted
{
    public function __construct(
        public readonly string $agentName,
        public readonly string $message,
        public readonly ?string $conversationId = null,
    ) {}
}
