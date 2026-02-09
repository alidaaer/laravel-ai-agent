<?php

namespace LaravelAIAgent\Exceptions;

use Exception;

class BudgetExceededException extends Exception
{
    public function __construct(
        string $message = "Budget exceeded",
        public readonly float $spent = 0,
        public readonly float $limit = 0,
        int $code = 402,
    ) {
        parent::__construct($message, $code);
    }
}
