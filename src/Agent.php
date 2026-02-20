<?php

namespace LaravelAIAgent;

use Illuminate\Support\Str;
use LaravelAIAgent\Contracts\DriverInterface;
use LaravelAIAgent\Contracts\MemoryInterface;
use LaravelAIAgent\Drivers\OpenAIDriver;
use LaravelAIAgent\Drivers\AnthropicDriver;
use LaravelAIAgent\Drivers\GeminiDriver;
use LaravelAIAgent\Drivers\DeepSeekDriver;
use LaravelAIAgent\Events\AgentStarted;
use LaravelAIAgent\Events\AgentCompleted;
use LaravelAIAgent\Memory\SessionMemory;
use LaravelAIAgent\Tools\ToolDiscovery;
use LaravelAIAgent\Tools\ToolRegistry;
use LaravelAIAgent\Tools\ToolExecutor;
use LaravelAIAgent\Tools\ToolValidator;
use LaravelAIAgent\Security\SecurityGuard;
use LaravelAIAgent\Security\AuditLogger;

class Agent
{
    protected string $name = 'Agent';
    protected ?DriverInterface $driver = null;
    protected ?MemoryInterface $memory = null;
    protected ToolRegistry $toolRegistry;
    protected ToolExecutor $toolExecutor;
    protected ToolDiscovery $toolDiscovery;
    
    protected string $conversationId;
    protected ?string $systemPrompt = null;
    protected ?string $agentScope = null;
    protected array $context = [];
    protected array $options = [];
    protected int $maxIterations = 10;
    protected array $middleware = [];
    protected ?array $structuredSchema = null;

    /**
     * Create a new Agent instance.
     */
    public static function make(string $name = 'Agent'): self
    {
        return new self($name);
    }

    public function __construct(string $name = 'Agent', bool $skipAutoDiscovery = false)
    {
        $this->name = $name;
        $this->conversationId = (string) Str::uuid();
        $this->toolRegistry = new ToolRegistry();
        $this->toolDiscovery = new ToolDiscovery();
        $this->toolExecutor = new ToolExecutor($this->toolRegistry, new ToolValidator());
        
        // Auto-discover tools if enabled (skip when agentScope will re-discover)
        if (!$skipAutoDiscovery && config('ai-agent.discovery.enabled', true)) {
            $this->autoDiscoverTools();
        }
    }

    /**
     * Set the AI driver.
     */
    public function driver(string $driver): self
    {
        $this->driver = $this->resolveDriver($driver);
        return $this;
    }

    /**
     * Set the model.
     */
    public function model(string $model): self
    {
        $this->getDriver()->setModel($model);
        return $this;
    }

    /**
     * Set the system prompt.
     */
    public function system(string $prompt): self
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    /**
     * Allow specific tool classes.
     * If agentScope is set, only tools allowed for that agent will be registered.
     */
    public function tools(array $classes): self
    {
        // Clear auto-discovered tools — explicit tools() replaces them
        $this->toolRegistry->clear();

        $tools = $this->agentScope
            ? $this->toolDiscovery->discoverForAgent($classes, $this->agentScope)
            : $this->toolDiscovery->discoverPublic($classes);

        \Illuminate\Support\Facades\Log::debug('AI Agent: tools() called', [
            'classes' => $classes,
            'agent_scope' => $this->agentScope,
            'discovered_count' => count($tools),
            'discovered_names' => array_column($tools, 'name'),
        ]);

        $this->toolRegistry->register($tools);
        return $this;
    }

    /**
     * Get registered tool names (for debugging).
     */
    public function getRegisteredToolNames(): array
    {
        return $this->toolRegistry->names();
    }

    /**
     * Disable all tools.
     */
    public function withoutTools(): self
    {
        $this->toolRegistry->clear();
        return $this;
    }

    /**
     * Scope tools to a specific agent.
     * Only tools with matching agents param (or null) will be available.
     */
    public function agentScope(string $agentName): self
    {
        $this->agentScope = $agentName;
        $this->toolRegistry->clear();
        $this->autoDiscoverToolsForAgent($agentName);
        return $this;
    }

    /**
     * Bind context from Eloquent models.
     */
    public function for(...$models): self
    {
        foreach ($models as $model) {
            if (is_object($model) && method_exists($model, 'toArray')) {
                $className = class_basename($model);
                $this->context[$className] = $model->toArray();
                
                // Add to system prompt
                $json = json_encode($model->toArray(), JSON_UNESCAPED_UNICODE);
                $this->systemPrompt = ($this->systemPrompt ?? '') . 
                    "\n\nContext - {$className}:\n{$json}";
            }
        }
        return $this;
    }

    /**
     * Add custom context.
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Continue an existing conversation.
     */
    public function conversation(string $id): self
    {
        $this->conversationId = $id;
        return $this;
    }

    /**
     * Set the temperature.
     */
    public function temperature(float $temp): self
    {
        $this->options['temperature'] = $temp;
        return $this;
    }

    /**
     * Set max tokens.
     */
    public function maxTokens(int $tokens): self
    {
        $this->options['max_tokens'] = $tokens;
        return $this;
    }

    /**
     * Set middleware pipeline for this agent.
     *
     * @param array<class-string|object> $middleware
     */
    public function withMiddleware(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Set structured output schema (JSON Schema).
     * The AI will respond in JSON matching this schema.
     */
    public function structured(?array $schema): self
    {
        $this->structuredSchema = $schema;
        return $this;
    }

    /**
     * Send a message and get a response.
     */
    public function chat(string $message): string
    {
        // Initialize security guard
        $security = $this->getSecurityGuard();
        $loggingEnabled = config('ai-agent.logging.enabled', true);
        
        // Content Moderation - validate input
        if (config('ai-agent.security.enabled', true)) {
            $validation = $security->validateInput($message);
            
            if (!$validation['valid']) {
                return '⚠️ ' . $validation['error'];
            }
            
            $message = $validation['sanitized'];
        }

        event(new AgentStarted($this->name, $message, $this->conversationId));

        $driver = $this->getDriver();
        $memory = $this->getMemory();

        // Get conversation history for LLM (limited context)
        $history = $memory->recallForLLM($this->conversationId);

        if ($loggingEnabled) {
            \Illuminate\Support\Facades\Log::debug('AI Agent: Memory state', [
                'conversation_id' => $this->conversationId,
                'history_count' => count($history),
                'history_roles' => array_map(fn($m) => $m['role'] ?? 'unknown', $history),
                'has_tool_calls_in_history' => count(array_filter($history, fn($m) => !empty($m['tool_calls']))),
                'memory_class' => get_class($memory),
            ]);
        }


        // Build options with full system prompt
        $options = $this->buildOptions($security);

        // Add structured output schema if set
        if ($this->structuredSchema) {
            $options['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'structured_output',
                    'strict' => true,
                    'schema' => $this->structuredSchema,
                ],
            ];
        }

        // Store user message
        $memory->remember($this->conversationId, ['role' => 'user', 'content' => $message]);

        // Apply middleware pipeline if configured
        if (!empty($this->middleware)) {
            $prompt = new AgentPrompt(
                message: $message,
                system: $options['system'] ?? null,
                history: $history,
                options: $options,
                tools: array_keys($this->toolRegistry->all()),
                conversationId: $this->conversationId,
            );

            $response = app(\Illuminate\Pipeline\Pipeline::class)
                ->send($prompt)
                ->through($this->middleware)
                ->then(function (AgentPrompt $prompt) use ($driver, $security, $memory) {
                    // Use prompt's options (middleware may have modified them)
                    $opts = $prompt->options;
                    if ($prompt->system) {
                        $opts['system'] = $prompt->system;
                    }
                    return $this->runLoop($driver, $prompt->message, $prompt->history, $opts, $security, $memory);
                });
        } else {
            // Run the agent loop with security checks (no middleware)
            $response = $this->runLoop($driver, $message, $history, $options, $security, $memory);
        }

        if ($loggingEnabled) {
            \Illuminate\Support\Facades\Log::debug('AI Agent: runLoop result', [
                'content_length' => strlen($response->content),
                'content_preview' => mb_substr($response->content, 0, 200),
                'has_tool_calls' => $response->hasToolCalls(),
                'finish_reason' => $response->finishReason,
            ]);
        }

        // Output Sanitization - clean response
        $content = $response->content;
        if (config('ai-agent.security.enabled', true)) {
            $content = $security->sanitizeOutput($content);

            if ($loggingEnabled) {
                \Illuminate\Support\Facades\Log::debug('AI Agent: After sanitizeOutput', [
                    'content_length' => strlen($content),
                    'content_preview' => mb_substr($content, 0, 200),
                ]);
            }
        }

        // Store assistant response
        $memory->remember($this->conversationId, ['role' => 'assistant', 'content' => $content]);

        event(new AgentCompleted($this->name, $response, $this->conversationId));

        return $content;
    }

    /**
     * Stream a response.
     */
    public function stream(string $message, callable $onChunk): void
    {
        // Initialize security guard
        $security = $this->getSecurityGuard();

        // Content Moderation - validate input
        if (config('ai-agent.security.enabled', true)) {
            $validation = $security->validateInput($message);

            if (!$validation['valid']) {
                return;
            }

            $message = $validation['sanitized'];
        }

        $driver = $this->getDriver();
        $memory = $this->getMemory();
        $history = $memory->recallForLLM($this->conversationId);

        // Build options with full system prompt
        $options = $this->buildOptions($security);

        $memory->remember($this->conversationId, ['role' => 'user', 'content' => $message]);

        // Apply middleware pipeline if configured
        if (!empty($this->middleware)) {
            $prompt = new AgentPrompt(
                message: $message,
                system: $options['system'] ?? null,
                history: $history,
                options: $options,
                tools: array_keys($this->toolRegistry->all()),
                conversationId: $this->conversationId,
            );

            app(\Illuminate\Pipeline\Pipeline::class)
                ->send($prompt)
                ->through($this->middleware)
                ->then(function (AgentPrompt $prompt) use ($driver, $onChunk, $security, $memory) {
                    $opts = $prompt->options;
                    if ($prompt->system) {
                        $opts['system'] = $prompt->system;
                    }
                    $response = $driver->stream($prompt->message, $this->toolRegistry->all(), $prompt->history, $onChunk);

                    $content = $response->content;
                    if (config('ai-agent.security.enabled', true)) {
                        $content = $security->sanitizeOutput($content);
                    }

                    $memory->remember($this->conversationId, ['role' => 'assistant', 'content' => $content]);
                    return $response;
                });
        } else {
            $response = $driver->stream($message, $this->toolRegistry->all(), $history, $onChunk);

            // Output Sanitization
            $content = $response->content;
            if (config('ai-agent.security.enabled', true)) {
                $content = $security->sanitizeOutput($content);
            }

            $memory->remember($this->conversationId, ['role' => 'assistant', 'content' => $content]);
        }
    }

    /**
     * Chat with real-time SSE events.
     * 
     * @param string $message The user message
     * @param callable $onEvent Callback: fn(string $event, array $data) => void
     * @return string The final response content
     */
    public function chatWithEvents(string $message, callable $onEvent): string
    {
        $security = $this->getSecurityGuard();

        // Content Moderation
        if (config('ai-agent.security.enabled', true)) {
            $validation = $security->validateInput($message);
            if (!$validation['valid']) {
                $onEvent('error', ['message' => $validation['error']]);
                return '⚠️ ' . $validation['error'];
            }
            $message = $validation['sanitized'];
        }

        event(new AgentStarted($this->name, $message, $this->conversationId));

        $driver = $this->getDriver();
        $memory = $this->getMemory();
        $history = $memory->recallForLLM($this->conversationId);

        // Build options with full system prompt
        $options = $this->buildOptions($security);

        // Add structured output schema if set
        if ($this->structuredSchema) {
            $options['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'structured_output',
                    'strict' => true,
                    'schema' => $this->structuredSchema,
                ],
            ];
        }

        $memory->remember($this->conversationId, ['role' => 'user', 'content' => $message]);

        // Send initial thinking event
        $onEvent('thinking', []);

        // Run the loop with events
        $toolExecutionCount = 0;

        // Apply middleware pipeline if configured
        if (!empty($this->middleware)) {
            $prompt = new AgentPrompt(
                message: $message,
                system: $options['system'] ?? null,
                history: $history,
                options: $options,
                tools: array_keys($this->toolRegistry->all()),
                conversationId: $this->conversationId,
            );

            $response = app(\Illuminate\Pipeline\Pipeline::class)
                ->send($prompt)
                ->through($this->middleware)
                ->then(function (AgentPrompt $prompt) use ($driver, $security, $memory, $onEvent, &$toolExecutionCount) {
                    $opts = $prompt->options;
                    if ($prompt->system) {
                        $opts['system'] = $prompt->system;
                    }
                    return $this->runLoop($driver, $prompt->message, $prompt->history, $opts, $security, $memory, $onEvent, $toolExecutionCount);
                });
        } else {
            $response = $this->runLoop($driver, $message, $history, $options, $security, $memory, $onEvent, $toolExecutionCount);
        }

        // Output Sanitization
        $content = $response->content;
        if (config('ai-agent.security.enabled', true)) {
            $content = $security->sanitizeOutput($content);
        }

        // Store response with task count metadata
        $metadata = ['task_count' => $toolExecutionCount];
        $memory->remember($this->conversationId, [
            'role' => 'assistant', 
            'content' => $content,
            'metadata' => $metadata
        ]);

        event(new AgentCompleted($this->name, $response, $this->conversationId));

        // Send final done event
        $onEvent('done', ['content' => $content]);

        return $content;
    }

    /**
     * Build system prompt options (shared between chat, chatWithEvents, stream).
     */
    protected function buildOptions(?SecurityGuard $security = null): array
    {
        $options = $this->options;
        $systemParts = [];

        if ($this->systemPrompt) {
            $systemParts[] = $this->systemPrompt;
        }

        // Add default system prompt (formatting instructions)
        $defaultPrompt = config('ai-agent.default_system_prompt');
        if ($defaultPrompt === null) {
            $promptFile = __DIR__ . '/../resources/prompts/default.txt';
            if (file_exists($promptFile)) {
                $defaultPrompt = file_get_contents($promptFile);
            }
        }
        if ($defaultPrompt) {
            $systemParts[] = $defaultPrompt;
        }

        // Add Smart Resolution instructions if enabled
        if (config('ai-agent.smart_resolution.enabled', false)) {
            $systemParts[] = $this->getSmartResolutionPrompt();
        }

        // Add security prompt (hardening)
        if ($security && config('ai-agent.security.enabled', true)) {
            $securityPrompt = $security->getSecurityPrompt();
            if ($securityPrompt) {
                $systemParts[] = $securityPrompt;
            }
        }

        if (!empty($systemParts)) {
            $options['system'] = implode("\n\n---\n\n", $systemParts);
        }

        return $options;
    }

    /**
     * Run the agent loop (handle tool calls).
     * Unified method that handles both regular chat and SSE events.
     *
     * @param DriverInterface $driver
     * @param string $message
     * @param array $history
     * @param array $options
     * @param SecurityGuard|null $security
     * @param MemoryInterface|null $memory
     * @param callable|null $onEvent Optional SSE event callback
     * @param int $toolExecutionCount Reference counter for tool executions
     * @return AgentResponse
     */
    protected function runLoop(
        DriverInterface $driver,
        string $message,
        array $history,
        array $options,
        ?SecurityGuard $security = null,
        ?MemoryInterface $memory = null,
        ?callable $onEvent = null,
        int &$toolExecutionCount = 0
    ): AgentResponse {
        $maxIterations = config('ai-agent.security.max_iterations', $this->maxIterations);
        $iterations = 0;
        $tools = $this->toolRegistry->all();
        $originalMessage = $message;
        $lastToolResults = [];
        $loggingEnabled = config('ai-agent.logging.enabled', true);

        if ($loggingEnabled) {
            \Illuminate\Support\Facades\Log::debug('AI Agent: runLoop start', [
                'registered_tools' => array_keys($tools),
                'tools_count' => count($tools),
                'history_count' => count($history),
                'agent_scope' => $this->agentScope,
            ]);
        }

        while ($iterations < $maxIterations) {
            $iterations++;

            // Check iteration limit with security
            if ($security && !$security->checkIterationLimit($iterations)) {
                $iterationMsg = trans('ai-agent::messages.max_iterations_reached');
                if ($onEvent) {
                    $onEvent('error', ['message' => $iterationMsg]);
                }
                return new AgentResponse(
                    content: '⚠️ ' . $iterationMsg,
                    toolCalls: [],
                    usage: [],
                    finishReason: 'iteration_limit'
                );
            }

            // Event: thinking before LLM call (skip first iteration — already sent by chatWithEvents)
            if ($onEvent && $iterations > 1) {
                $onEvent('thinking', []);
            }

            // Call the LLM
            if ($loggingEnabled) {
                \Illuminate\Support\Facades\Log::debug('AI Agent: LLM prompt', [
                    'iteration' => $iterations,
                    'tools_sent' => count($tools),
                    'tool_names_sent' => array_keys($tools),
                    'history_count' => count($history),
                    'message_preview' => mb_substr($message, 0, 100),
                    'has_system' => isset($options['system']),
                ]);
            }

            $response = $driver->prompt($message, $tools, $history, $options);

            if ($loggingEnabled) {
                \Illuminate\Support\Facades\Log::debug('AI Agent: LLM response', [
                    'iteration' => $iterations,
                    'content_length' => strlen($response->content),
                    'has_tool_calls' => $response->hasToolCalls(),
                    'tool_calls_count' => count($response->toolCalls),
                    'tool_call_names' => array_column($response->toolCalls, 'name'),
                    'finish_reason' => $response->finishReason,
                ]);
            }

            // If no tool calls, we're done
            if (!$response->hasToolCalls()) {
                // If response is empty but we have tool results, return the tool results
                if (empty($response->content) && !empty($lastToolResults)) {
                    $resultsText = collect($lastToolResults)
                        ->filter(fn($r) => $r['success'])
                        ->map(fn($r) => is_string($r['result']) ? $r['result'] : json_encode($r['result'], JSON_UNESCAPED_UNICODE))
                        ->join("\n");
                    
                    return new AgentResponse(
                        content: $resultsText ?: trans('ai-agent::messages.tool_executed_successfully'),
                        toolCalls: [],
                        usage: $response->usage,
                        finishReason: $response->finishReason
                    );
                }
                return $response;
            }

            // Security check for tool calls
            if ($security) {
                foreach ($response->toolCalls as $toolCall) {
                    $check = $security->checkToolCall(
                        $toolCall['name'],
                        $toolCall['arguments'] ?? []
                    );
                    
                    if (!$check['allowed']) {
                        if ($onEvent) {
                            $onEvent('error', ['message' => $check['reason']]);
                        }
                        return new AgentResponse(
                            content: '⚠️ ' . $check['reason'],
                            toolCalls: [],
                            usage: $response->usage,
                            finishReason: 'security_blocked'
                        );
                    }
                    
                    // Log tool call for audit
                    if (config('ai-agent.security.audit.enabled', true)) {
                        app(AuditLogger::class)->logToolCall(
                            $toolCall['name'],
                            $toolCall['arguments'] ?? [],
                            auth()->id(),
                            $this->conversationId
                        );
                    }
                }
            }

            // Event: tool_start for each tool (SSE only)
            if ($onEvent) {
                foreach ($response->toolCalls as $toolCall) {
                    $toolDef = $this->toolRegistry->find($toolCall['name']);
                    $onEvent('tool_start', [
                        'name' => $toolCall['name'],
                        'description' => $toolDef['description'] ?? null,
                        'arguments' => $toolCall['arguments'] ?? [],
                    ]);
                }
            }

            // Execute tool calls
            $toolResults = $this->toolExecutor->executeMany($response->toolCalls, $this->context);
            $lastToolResults = $toolResults;

            // Track tool execution count
            $toolExecutionCount += count($response->toolCalls);

            // Event: tool_done for each tool (SSE only)
            if ($onEvent) {
                foreach ($toolResults as $result) {
                    $toolDef = $this->toolRegistry->find($result['name']);
                    $onEvent('tool_done', [
                        'name' => $result['name'],
                        'description' => $toolDef['description'] ?? null,
                        'success' => $result['success'],
                    ]);
                }
            }

            // Log tool calls and results
            if ($loggingEnabled) {
                foreach ($toolResults as $result) {
                    \Illuminate\Support\Facades\Log::debug('AI Agent: Tool Call', [
                        'tool' => $result['name'],
                        'success' => $result['success'],
                        'result' => $result['success'] ? $result['result'] : null,
                        'error' => $result['success'] ? null : $result['error'],
                        'conversation_id' => $this->conversationId,
                    ]);
                }
            }

            // For the first iteration, add the user's original message to history
            if ($iterations === 1) {
                $history[] = [
                    'role' => 'user',
                    'content' => $originalMessage,
                ];
            }

            // Add assistant's response with tool calls to history
            $assistantMsg = [
                'role' => 'assistant',
                'content' => $response->content,
                'tool_calls' => $response->toolCalls,
            ];
            $history[] = $assistantMsg;
            if ($memory) {
                $memory->remember($this->conversationId, $assistantMsg);
            }

            // Add tool results to history  
            foreach ($toolResults as $result) {
                $toolMsg = [
                    'role' => 'tool',
                    'tool_call_id' => $result['tool_call_id'],
                    'tool_name' => $result['name'],
                    'content' => $result['success'] 
                        ? json_encode($result['result'], JSON_UNESCAPED_UNICODE)
                        : "Error: " . $result['error'],
                ];
                $history[] = $toolMsg;
                if ($memory) {
                    $memory->remember($this->conversationId, $toolMsg);
                }
            }

            // Continue with a message asking LLM to respond based on tool results
            $message = trans('ai-agent::messages.answer_based_on_tool_results');
            
            // Configurable delay between iterations (some APIs like Gemini need it)
            $loopDelay = config('ai-agent.performance.loop_delay_ms', 0);
            if ($loopDelay > 0) {
                usleep($loopDelay * 1000);
            }
        }

        throw new \RuntimeException("Agent exceeded maximum iterations ({$this->maxIterations})");
    }

    /**
     * Get or create the driver instance.
     */
    protected function getDriver(): DriverInterface
    {
        if ($this->driver === null) {
            $default = config('ai-agent.default', 'openai');
            $this->driver = $this->resolveDriver($default);
        }

        return $this->driver;
    }

    /**
     * Resolve a driver by name.
     */
    protected function resolveDriver(string $name): DriverInterface
    {
        return match ($name) {
            'openai' => new OpenAIDriver(),
            'anthropic' => new AnthropicDriver(),
            'gemini' => new GeminiDriver(),
            'deepseek' => new DeepSeekDriver(),
            'openrouter' => new Drivers\OpenRouterDriver(),
            default => throw new \InvalidArgumentException("Unknown driver: {$name}"),
        };
    }

    /**
     * Get or create the memory instance.
     */
    protected function getMemory(): MemoryInterface
    {
        if ($this->memory === null) {
            $driver = config('ai-agent.memory.driver', 'session');
            $this->memory = $this->resolveMemory($driver);

            // Scope memory to this agent for conversation isolation
            if ($this->agentScope) {
                $this->memory->forAgent($this->agentScope);
            }
        }

        return $this->memory;
    }

    /**
     * Resolve memory driver by name.
     */
    public static function resolveMemory(string $name): MemoryInterface
    {
        return match ($name) {
            'database' => new Memory\DatabaseMemory(),
            'session' => new SessionMemory(),
            'null' => new Memory\NullMemory(),
            default => new SessionMemory(),
        };
    }

    /**
     * Auto-discover tools from configured paths.
     */
    protected function autoDiscoverTools(): void
    {
        $paths = config('ai-agent.discovery.paths', []);
        $classes = $this->findClassesInPaths($paths);

        if (!empty($classes)) {
            $tools = $this->agentScope
                ? $this->toolDiscovery->discoverForAgent($classes, $this->agentScope)
                : $this->toolDiscovery->discoverPublic($classes);
            $this->toolRegistry->register($tools);
        }
    }

    /**
     * Auto-discover tools filtered for a specific agent.
     */
    protected function autoDiscoverToolsForAgent(string $agentName): void
    {
        $paths = config('ai-agent.discovery.paths', []);
        $classes = $this->findClassesInPaths($paths);

        if (!empty($classes)) {
            $tools = $this->toolDiscovery->discoverForAgent($classes, $agentName);
            $this->toolRegistry->register($tools);
        }
    }

    /**
     * Find all PHP classes in configured paths.
     */
    protected function findClassesInPaths(array $paths): array
    {
        $classes = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $classes = array_merge($classes, $this->findClassesInPath($path));
            }
        }

        return $classes;
    }

    /**
     * Find PHP classes in a directory (recursive).
     */
    protected function findClassesInPath(string $path): array
    {
        $classes = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            
            if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) &&
                preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                $fqcn = $nsMatch[1] . '\\' . $classMatch[1];

                // Skip BaseAgent subclasses — they are not tool providers
                try {
                    if (class_exists($fqcn) && is_subclass_of($fqcn, \LaravelAIAgent\BaseAgent::class)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    /**
     * Get the conversation ID.
     */
    public function getConversationId(): string
    {
        return $this->conversationId;
    }

    /**
     * Clear conversation history.
     */
    public function forget(): self
    {
        $this->getMemory()->forget($this->conversationId);
        return $this;
    }

    /**
     * Get the security guard instance.
     */
    protected function getSecurityGuard(): SecurityGuard
    {
        return app(SecurityGuard::class);
    }

    /**
     * Get the Smart Resolution prompt instructions.
     * When enabled, this instructs the AI to search for records by name
     * when the user provides a name instead of an ID.
     */
    protected function getSmartResolutionPrompt(): string
    {
        return <<<'PROMPT'
## Smart Name-to-ID Resolution:
When a tool requires an ID but the user provides a name/title instead:
1. First, search for the item using the appropriate search tool (e.g., searchProducts, getOrders, listCategories)
2. Use the ID from the search result to call the actual operation tool
3. If no matching item is found, inform the user

Examples:
- "update iPhone price" → First call searchProducts("iPhone"), then use the returned ID to call updateProduct
- "delete order for Ahmed" → First call getOrders with customer filter, then use the ID to call deleteOrder
- "add product to Electronics" → First search for the category, then use its ID

This ensures accurate operations without guessing IDs.
PROMPT;
    }
}
