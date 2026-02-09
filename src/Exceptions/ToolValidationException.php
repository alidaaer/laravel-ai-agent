<?php

namespace LaravelAIAgent\Exceptions;

use Exception;

class ToolValidationException extends Exception
{
    public function __construct(
        string $message,
        public readonly array $errors = [],
        int $code = 422,
    ) {
        parent::__construct($message, $code);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
