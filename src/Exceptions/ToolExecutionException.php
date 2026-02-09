<?php

namespace LaravelAIAgent\Exceptions;

use Exception;

class ToolExecutionException extends Exception
{
    public function __construct(string $message = "Tool execution failed", int $code = 500)
    {
        parent::__construct($message, $code);
    }
}
