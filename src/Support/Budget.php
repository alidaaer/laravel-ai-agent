<?php

namespace LaravelAIAgent\Support;

use Illuminate\Support\Facades\Cache;
use LaravelAIAgent\Exceptions\BudgetExceededException;
use LaravelAIAgent\Events\BudgetWarning;
use LaravelAIAgent\Events\BudgetExceeded;

class Budget
{
    protected float $limit;
    protected float $spent = 0;
    protected string $sessionId;

    /**
     * Pricing per 1M tokens (USD).
     */
    protected array $pricing = [
        'openai' => [
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        ],
        'anthropic' => [
            'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
            'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25],
        ],
    ];

    public function __construct(float $limit, string $sessionId)
    {
        $this->limit = $limit;
        $this->sessionId = $sessionId;
        $this->spent = (float) Cache::get("ai_budget_{$sessionId}", 0);
    }

    /**
     * Check budget and charge for the request.
     *
     * @throws BudgetExceededException
     */
    public function checkAndCharge(string $driver, string $model, array $usage): void
    {
        $cost = $this->calculateCost($driver, $model, $usage);

        if (($this->spent + $cost) > $this->limit) {
            event(new BudgetExceeded($this->sessionId, $this->spent, $this->limit));
            
            throw new BudgetExceededException(
                "Budget exceeded. Limit: \${$this->limit}, Spent: \${$this->spent}, Required: \${$cost}",
                $this->spent,
                $this->limit
            );
        }

        $this->spent += $cost;
        Cache::put("ai_budget_{$this->sessionId}", $this->spent, now()->addDay());

        // Warning at threshold
        $threshold = config('ai-agent.budget.warning_threshold', 0.80);
        if ($this->spent >= ($this->limit * $threshold)) {
            event(new BudgetWarning($this->sessionId, $this->spent, $this->limit));
        }
    }

    /**
     * Calculate cost for a request.
     */
    public function calculateCost(string $driver, string $model, array $usage): float
    {
        $pricing = $this->pricing[$driver][$model] ?? ['input' => 0, 'output' => 0];

        $inputTokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0;

        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return $inputCost + $outputCost;
    }

    /**
     * Get remaining budget.
     */
    public function getRemaining(): float
    {
        return max(0, $this->limit - $this->spent);
    }

    /**
     * Get spent amount.
     */
    public function getSpent(): float
    {
        return $this->spent;
    }

    /**
     * Get the limit.
     */
    public function getLimit(): float
    {
        return $this->limit;
    }

    /**
     * Reset the budget.
     */
    public function reset(): void
    {
        $this->spent = 0;
        Cache::forget("ai_budget_{$this->sessionId}");
    }

    /**
     * Add custom pricing for a model.
     */
    public function addPricing(string $driver, string $model, float $inputCost, float $outputCost): self
    {
        $this->pricing[$driver][$model] = [
            'input' => $inputCost,
            'output' => $outputCost,
        ];

        return $this;
    }
}
