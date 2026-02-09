<?php

namespace LaravelAIAgent\Events;

use LaravelAIAgent\AgentResponse;

class AgentCompleted
{
    public function __construct(
        public readonly string $agentName,
        public readonly AgentResponse $response,
        public readonly ?string $conversationId = null,
    ) {}
}
