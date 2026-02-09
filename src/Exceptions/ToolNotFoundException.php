<?php

namespace LaravelAIAgent\Exceptions;

use Exception;

class ToolNotFoundException extends Exception
{
    public function __construct(string $message = "Tool not found", int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
