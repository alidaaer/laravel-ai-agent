<?php

namespace LaravelAIAgent\Tools;

use Illuminate\Support\Facades\Cache;
use LaravelAIAgent\Attributes\AsAITool;
use LaravelAIAgent\Attributes\Rules;
use LaravelAIAgent\Support\SchemaConverter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class ToolDiscovery
{
    protected SchemaConverter $schemaConverter;
    protected SmartSchemaGenerator $smartGenerator;

    public function __construct()
    {
        $this->schemaConverter = new SchemaConverter();
        $this->smartGenerator = new SmartSchemaGenerator();
    }

    /**
     * Discover tools from given classes.
     *
     * @param array $classes Array of class names
     * @return array
     */
    public function discover(array $classes): array
    {
        if (config('ai-agent.discovery.cache', true)) {
            $cacheKey = 'ai_agent_tools_' . md5(serialize($classes));
            $ttl = config('ai-agent.discovery.cache_ttl', 3600);

            return Cache::remember($cacheKey, $ttl, fn() => $this->scanClasses($classes));
        }

        return $this->scanClasses($classes);
    }

    /**
     * Scan classes for tools.
     */
    protected function scanClasses(array $classes): array
    {
        $tools = [];

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Support both #[AsAITool] and #[Tool]
                $attributes = $method->getAttributes(AsAITool::class);
                
                if (empty($attributes)) {
                    $attributes = $method->getAttributes(\LaravelAIAgent\Attributes\Tool::class);
                }

                if (empty($attributes)) {
                    continue;
                }

                $toolAttr = $attributes[0]->newInstance();
                $tools[] = $this->buildToolDefinition($class, $method, $toolAttr);
            }
        }

        return $tools;
    }

    /**
     * Build a tool definition from a method.
     * Now uses SmartSchemaGenerator for auto-inference.
     */
    protected function buildToolDefinition(string $class, ReflectionMethod $method, AsAITool $attr): array
    {
        // Use SmartSchemaGenerator for the new auto-inference approach
        return $this->smartGenerator->generateToolDefinition($class, $method, $attr);
    }

    /**
     * Extract parameters from a method.
     * @deprecated Use SmartSchemaGenerator instead
     */
    protected function extractParameters(ReflectionMethod $method): array
    {
        $params = [];

        foreach ($method->getParameters() as $param) {
            $rulesAttr = $param->getAttributes(Rules::class);
            $rules = !empty($rulesAttr) ? $rulesAttr[0]->newInstance() : null;

            $params[$param->getName()] = [
                'type' => $this->getParameterType($param),
                'required' => !$param->isOptional(),
                'default' => $param->isOptional() ? $param->getDefaultValue() : null,
                'rules' => $rules?->rules ?? '',
                'description' => $rules?->description ?? $this->generateDescription($param),
                'example' => $rules?->example,
            ];
        }

        return $params;
    }

    /**
     * Get the type of a parameter.
     */
    protected function getParameterType(ReflectionParameter $param): string
    {
        $type = $param->getType();

        if ($type === null) {
            return 'string';
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    /**
     * Generate a description from parameter name.
     */
    protected function generateDescription(ReflectionParameter $param): string
    {
        $name = $param->getName();
        // Convert camelCase to words
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
        return ucfirst(strtolower($words));
    }

    /**
     * Discover only public tools (not restricted to any specific agent).
     * Returns tools where agents is null AND no scoped version of the same name exists.
     */
    public function discoverPublic(array $classes): array
    {
        $allTools = $this->discover($classes);

        return array_values(array_filter($allTools, function (array $tool) {
            return $tool['agents'] === null;
        }));
    }

    /**
     * Discover tools filtered for a specific agent.
     * Only returns tools where agents is null (all) or contains the agent name.
     *
     * Security: If a tool name has both unscoped (agents: null) and scoped versions,
     * the unscoped version is blocked to prevent bypassing agent restrictions.
     * Explicitly scoped versions (agents: ['shop'], agents: ['admin']) are always
     * evaluated independently — allowing intentional per-agent implementations.
     */
    public function discoverForAgent(array $classes, string $agentName): array
    {
        $allTools = $this->discover($classes);

        // First pass: find tool names that have at least one scoped version
        $hasScoped = [];
        foreach ($allTools as $tool) {
            if ($tool['agents'] !== null) {
                $hasScoped[$tool['name']] = true;
            }
        }

        // Second pass: filter tools
        return array_values(array_filter($allTools, function (array $tool) use ($agentName, $hasScoped) {
            // Unscoped tool (agents: null)
            if ($tool['agents'] === null) {
                // Block if a scoped version of the same name exists (prevent bypass)
                if (isset($hasScoped[$tool['name']])) {
                    return false;
                }
                return true;
            }

            // Scoped tool: allow only if this agent is in the list
            return in_array($agentName, $tool['agents'], true);
        }));
    }

    /**
     * Clear the tools cache for specific classes or all cached tools.
     */
    public function clearCache(?array $classes = null): void
    {
        if ($classes) {
            $cacheKey = 'ai_agent_tools_' . md5(serialize($classes));
            Cache::forget($cacheKey);
            return;
        }

        // Reconstruct cache key from configured discovery paths
        $paths = config('ai-agent.discovery.paths', []);
        $discoveredClasses = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '/*.php');
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) &&
                        preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                        $discoveredClasses[] = $nsMatch[1] . '\\' . $classMatch[1];
                    }
                }
            }
        }

        if (!empty($discoveredClasses)) {
            $cacheKey = 'ai_agent_tools_' . md5(serialize($discoveredClasses));
            Cache::forget($cacheKey);
        }
    }
}

