<?php

namespace LaravelAIAgent\Events;

class ToolExecuted
{
    public function __construct(
        public readonly array $tool,
        public readonly array $arguments,
        public readonly mixed $result,
    ) {}
}
