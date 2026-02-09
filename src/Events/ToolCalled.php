<?php

namespace LaravelAIAgent\Events;

class ToolCalled
{
    public function __construct(
        public readonly array $tool,
        public readonly array $arguments,
        public readonly array $context = [],
    ) {}
}
