<?php

namespace LaravelAIAgent\Tools;

use LaravelAIAgent\Events\ToolCalled;
use LaravelAIAgent\Events\ToolExecuted;
use LaravelAIAgent\Events\ToolFailed;
use LaravelAIAgent\Exceptions\ToolNotFoundException;
use LaravelAIAgent\Exceptions\ToolExecutionException;

class ToolExecutor
{
    protected ResultTransformer $resultTransformer;
    protected \LaravelAIAgent\AgentContext $context;

    /**
     * Cache for ReflectionMethod instances to avoid repeated reflection.
     */
    protected static array $reflectionCache = [];

    public function __construct(
        protected ToolRegistry $registry,
        protected ToolValidator $validator,
    ) {
        $this->resultTransformer = new ResultTransformer();
        $this->context = app(\LaravelAIAgent\AgentContext::class);
    }

    /**
     * Execute a tool by name with given arguments.
     *
     * @param string $toolName The tool name
     * @param array $arguments The arguments from LLM
     * @param array $context Additional context (user, permissions, etc.)
     * @return mixed
     */
    public function execute(string $toolName, array $arguments, array $context = []): mixed
    {
        // 1. Find the tool
        $tool = $this->registry->find($toolName);

        if (!$tool) {
            throw new ToolNotFoundException("Tool '{$toolName}' not found");
        }

        // 2. Fire ToolCalled event
        event(new ToolCalled($tool, $arguments, $context));

        try {
            // 3. Normalize arguments (camelCase → snake_case for PHP conventions)
            $normalizedArgs = $this->normalizeArguments($arguments);

            // 4. Validate arguments
            $validatedArgs = $this->validator->validate($tool, $normalizedArgs);

            // 4.5. Unwrap single-item arrays if needed (common AI quirk)
            $validatedArgs = $this->unwrapSingleItemArrays($tool, $validatedArgs);

            // 4. Check permission if required
            if ($tool['permission'] && !$this->checkPermission($tool['permission'], $context)) {
                throw new ToolExecutionException("Permission denied for tool '{$toolName}'");
            }

            // 5. Prepare arguments - inject Request if method expects it
            $preparedArgs = $this->prepareArguments($tool, $validatedArgs);

            // 6. Set context flag so methods can detect AI tool calls
            $this->context->enterToolCall($toolName);

            // 7. Execute the method
            $instance = app($tool['class']);
            $result = app()->call([$instance, $tool['method']], $preparedArgs);

            // 8. Clear context flag
            $this->context->exitToolCall();

            // 9. Transform result to AI-friendly format
            $result = $this->resultTransformer->transform($result);

            // 10. Fire ToolExecuted event
            event(new ToolExecuted($tool, $arguments, $result));

            return $result;

        } catch (\Throwable $e) {
            // Clear context flag on error too
            $this->context->exitToolCall();

            // Fire ToolFailed event
            event(new ToolFailed($tool, $arguments, $e));
            
            throw $e;
        }
    }

    /**
     * Prepare arguments for method execution.
     * If method expects a Request object, create one from the arguments.
     */
    protected function prepareArguments(array $tool, array $arguments): array
    {
        $class = $tool['class'];
        $method = $tool['method'];

        try {
            $cacheKey = $class . '::' . $method;
            $reflection = self::$reflectionCache[$cacheKey]
                ??= new \ReflectionMethod($class, $method);
            
            foreach ($reflection->getParameters() as $param) {
                $type = $param->getType();
                if (!$type) continue;

                $typeName = $type->getName();

                // Check if parameter is Request or subclass of Request
                if ($typeName === \Illuminate\Http\Request::class ||
                    (class_exists($typeName) && is_subclass_of($typeName, \Illuminate\Http\Request::class))) {
                    
                    // Create a Request object from arguments
                    $request = \Illuminate\Http\Request::create('/', 'POST', $arguments);
                    
                    // If it's a FormRequest subclass, use that instead
                    if ($typeName !== \Illuminate\Http\Request::class && class_exists($typeName)) {
                        $request = $typeName::createFrom($request);
                    }

                    return [$param->getName() => $request];
                }
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, just use original arguments
        }

        return $arguments;
    }

    /**
     * Normalize arguments keys from camelCase to snake_case.
     * This ensures AI responses (which often use camelCase) work with PHP conventions.
     * We keep both versions (original + snake_case) so either will work.
     */
    protected function normalizeArguments(array $arguments): array
    {
        $normalized = [];
        
        foreach ($arguments as $key => $value) {
            // Keep original key
            $normalized[$key] = $value;
            
            // Also add snake_case version if different
            $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
            if ($snakeKey !== $key) {
                // Recursively normalize nested arrays for snake_case version
                if (is_array($value)) {
                    $normalized[$snakeKey] = $this->normalizeNestedArray($value);
                } else {
                    $normalized[$snakeKey] = $value;
                }
            }
        }
        
        return $normalized;
    }

    /**
     * Recursively normalize nested arrays (for items like order items).
     */
    protected function normalizeNestedArray(array $array): array
    {
        // Check if it's an indexed array (list of items)
        if (array_is_list($array)) {
            return array_map(function ($item) {
                if (is_array($item)) {
                    return $this->normalizeArguments($item);
                }
                return $item;
            }, $array);
        }
        
        // It's an associative array, normalize keys
        return $this->normalizeArguments($array);
    }

    /**
     * Check if the current context has the required permission.
     */
    protected function checkPermission(string $permission, array $context): bool
    {
        // If user is in context and has can() method
        if (isset($context['user']) && method_exists($context['user'], 'can')) {
            return $context['user']->can($permission);
        }

        // If no user in context, check auth user
        $user = auth()->user();
        if ($user && method_exists($user, 'can')) {
            return $user->can($permission);
        }

        // Default: allow if no permission system
        return true;
    }

    /**
     * Execute multiple tool calls.
     */
    public function executeMany(array $toolCalls, array $context = []): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            try {
                $results[] = [
                    'tool_call_id' => $toolCall['id'] ?? null,
                    'name' => $toolCall['name'],
                    'result' => $this->execute($toolCall['name'], $toolCall['arguments'] ?? [], $context),
                    'success' => true,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'tool_call_id' => $toolCall['id'] ?? null,
                    'name' => $toolCall['name'],
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Unwrap single-item arrays that AI models sometimes create.
     * 
     * Some AI models wrap associative arrays in a numeric array:
     * {"data": [{"field": "value"}]} instead of {"data": {"field": "value"}}
     * 
     * This method detects and unwraps such cases when the method expects an array.
     */
    protected function unwrapSingleItemArrays(array $tool, array $arguments): array
    {
        try {
            $cacheKey = $tool['class'] . '::' . $tool['method'];
            $reflection = self::$reflectionCache[$cacheKey]
                ??= new \ReflectionMethod($tool['class'], $tool['method']);
            
            foreach ($reflection->getParameters() as $param) {
                $paramName = $param->getName();
                
                // Check if this parameter exists in arguments and is an array
                if (!isset($arguments[$paramName]) || !is_array($arguments[$paramName])) {
                    continue;
                }
                
                $value = $arguments[$paramName];
                
                // Check if it's a single-item array with numeric keys
                // that contains an associative array
                if (count($value) === 1 && isset($value[0]) && is_array($value[0])) {
                    // Check if the inner array has string keys (associative)
                    $innerArray = $value[0];
                    $hasStringKeys = count(array_filter(array_keys($innerArray), 'is_string')) > 0;
                    
                    if ($hasStringKeys) {
                        // Unwrap the array
                        $arguments[$paramName] = $innerArray;
                        
                        // Log this transformation for debugging
                        if (config('ai-agent.debug', false)) {
                            \Log::info('[AI-Agent] Unrapped single-item array', [
                                'tool' => $tool['name'],
                                'parameter' => $paramName,
                                'original' => $value,
                                'unwrapped' => $innerArray
                            ]);
                        }
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, just return original arguments
        }
        
        return $arguments;
    }
}
