<?php

namespace LaravelAIAgent\Tools;

use Illuminate\Support\Facades\Cache;
use LaravelAIAgent\Attributes\AsAITool;
use ReflectionClass;
use ReflectionMethod;

class ToolDiscovery
{
    protected SmartSchemaGenerator $smartGenerator;

    public function __construct()
    {
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
                // Look for #[AsAITool] attribute
                $attributes = $method->getAttributes(AsAITool::class);

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
     * The agents parameter supports both formats:
     *   - Class reference: agents: [AdminAgent::class]  (recommended — IDE-friendly)
     *   - Route name string: agents: ['admin-agent']     (simple alternative)
     *
     * Security: If a tool name has both unscoped (agents: null) and scoped versions,
     * the unscoped version is blocked to prevent bypassing agent restrictions.
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

            // Scoped tool: normalize agents to route names, then check
            $normalizedAgents = array_map(fn($a) => $this->resolveAgentName($a), $tool['agents']);
            return in_array($agentName, $normalizedAgents, true);
        }));
    }

    /**
     * Resolve an agent identifier to a route name.
     * Supports class references (AdminAgent::class) and plain strings ('admin-agent').
     */
    protected function resolveAgentName(string $agent): string
    {
        // If it's a class that extends BaseAgent, get its routeName()
        if (class_exists($agent) && is_subclass_of($agent, \LaravelAIAgent\BaseAgent::class)) {
            return $agent::routeName();
        }

        // Otherwise treat as a plain route name string
        return $agent;
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
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }
                    $content = file_get_contents($file->getPathname());
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

