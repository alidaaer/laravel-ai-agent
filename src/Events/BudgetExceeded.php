<?php

namespace LaravelAIAgent\Events;

class BudgetExceeded
{
    public function __construct(
        public readonly string $sessionId,
        public readonly float $spent,
        public readonly float $limit,
    ) {}
}
