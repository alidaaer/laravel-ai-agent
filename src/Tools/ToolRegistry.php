<?php

namespace LaravelAIAgent\Tools;

class ToolRegistry
{
    protected array $tools = [];

    /**
     * Register tools from discovery.
     */
    public function register(array $tools): void
    {
        foreach ($tools as $tool) {
            $this->tools[$tool['name']] = $tool;
        }
    }

    /**
     * Find a tool by name.
     */
    public function find(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Get all registered tools.
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Check if a tool exists.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get tools as JSON Schema for LLM.
     */
    public function toJsonSchema(): array
    {
        $functions = [];

        foreach ($this->tools as $tool) {
            $functions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['schema'],
                ],
            ];
        }

        return $functions;
    }

    /**
     * Get tool names.
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Clear all tools.
     */
    public function clear(): void
    {
        $this->tools = [];
    }
}
