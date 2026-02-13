<?php

namespace LaravelAIAgent\Console;

use Illuminate\Console\GeneratorCommand;

class MakeMiddlewareCommand extends GeneratorCommand
{
    protected $signature = 'make:ai-middleware {name : The name of the middleware}';

    protected $description = 'Create a new AI agent middleware class';

    protected $type = 'AI Middleware';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/middleware.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\AI\\Middleware';
    }
}
