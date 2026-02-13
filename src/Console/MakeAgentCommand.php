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

    /**
     * After the agent class is created, register it in config/ai-agent.php.
     */
    public function handle()
    {
        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        $this->registerInConfig();

        return $result;
    }

    /**
     * Add the agent class to the 'agents' array in config/ai-agent.php.
     */
    protected function registerInConfig(): void
    {
        $configPath = config_path('ai-agent.php');

        if (!file_exists($configPath)) {
            return;
        }

        $fqcn = $this->qualifyClass($this->getNameInput());
        $entry = '\\' . ltrim($fqcn, '\\') . '::class';

        $content = file_get_contents($configPath);

        // Already registered?
        if (str_contains($content, $entry)) {
            return;
        }

        // Find the 'agents' => [ ... ] array and append before the closing ]
        $pattern = "/('agents'\s*=>\s*\[)(.*?)(\])/s";

        if (preg_match($pattern, $content, $matches)) {
            $existing = rtrim($matches[2]);

            // Build the new entry with proper indentation
            $newEntry = "\n        {$entry},";

            $replacement = $matches[1] . $existing . $newEntry . "\n    " . $matches[3];

            $content = preg_replace($pattern, $replacement, $content, 1);

            file_put_contents($configPath, $content);

            $this->components->info("Agent registered in [config/ai-agent.php].");
        }
    }
}
