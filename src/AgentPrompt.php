<?php

namespace LaravelAIAgent;

class AgentPrompt
{
    public function __construct(
        public string $message,
        public ?string $system = null,
        public array $history = [],
        public array $options = [],
        public array $tools = [],
        public ?string $conversationId = null,
    ) {}

    /**
     * Get the prompt as an array.
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'system' => $this->system,
            'history' => $this->history,
            'options' => $this->options,
            'tools' => $this->tools,
            'conversation_id' => $this->conversationId,
        ];
    }
}
