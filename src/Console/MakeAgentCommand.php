<?php

namespace LaravelAIAgent\Console;

use Illuminate\Console\GeneratorCommand;

class MakeAgentCommand extends GeneratorCommand
{
    protected $signature = 'make:agent {name : The name of the agent}';

    protected $description = 'Create a new AI agent class';

    protected $type = 'Agent';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/agent.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\AI\\Agents';
    }
}
