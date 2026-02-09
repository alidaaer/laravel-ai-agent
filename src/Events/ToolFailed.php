<?php

namespace LaravelAIAgent\Events;

use Throwable;

class ToolFailed
{
    public function __construct(
        public readonly array $tool,
        public readonly array $arguments,
        public readonly Throwable $exception,
    ) {}
}
