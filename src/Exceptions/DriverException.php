<?php

namespace LaravelAIAgent\Exceptions;

use Exception;

class DriverException extends Exception
{
    public function __construct(
        string $message = "Driver error",
        public readonly ?array $response = null,
        int $code = 500,
    ) {
        parent::__construct($message, $code);
    }
}
