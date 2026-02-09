<?php

namespace LaravelAIAgent\Events;

class BudgetWarning
{
    public function __construct(
        public readonly string $sessionId,
        public readonly float $spent,
        public readonly float $limit,
    ) {}

    public function percentUsed(): float
    {
        return ($this->spent / $this->limit) * 100;
    }
}
