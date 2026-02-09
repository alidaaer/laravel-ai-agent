<?php

namespace LaravelAIAgent;

use Illuminate\Support\Str;
use LaravelAIAgent\Contracts\DriverInterface;
use LaravelAIAgent\Contracts\MemoryInterface;
use LaravelAIAgent\Drivers\OpenAIDriver;
use LaravelAIAgent\Drivers\AnthropicDriver;
use LaravelAIAgent\Drivers\GeminiDriver;
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
    protected array $context = [];
    protected array $options = [];
    protected int $maxIterations = 10;

    /**
     * Create a new Agent instance.
     */
    public static function make(string $name = 'Agent'): self
    {
        return new self($name);
    }

    public function __construct(string $name = 'Agent')
    {
        $this->name = $name;
        $this->conversationId = (string) Str::uuid();
        $this->toolRegistry = new ToolRegistry();
        $this->toolDiscovery = new ToolDiscovery();
        $this->toolExecutor = new ToolExecutor($this->toolRegistry, new ToolValidator());
        
        // Auto-discover tools if enabled
        if (config('ai-agent.discovery.enabled', true)) {
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
     */
    public function tools(array $classes): self
    {
        $tools = $this->toolDiscovery->discover($classes);
        $this->toolRegistry->register($tools);
        return $this;
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
     * Send a message and get a response.
     */
    public function chat(string $message): string
    {
        // Initialize security guard
        $security = $this->getSecurityGuard();
        
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

        // Get conversation history
        $history = $memory->recall($this->conversationId);

        // Build options
        $options = $this->options;
        
        // Build system prompt: user prompt + default prompt + security prompt
        $systemParts = [];
        
        if ($this->systemPrompt) {
            $systemParts[] = $this->systemPrompt;
        }
        
        // Add default system prompt (formatting instructions)
        $defaultPrompt = config('ai-agent.default_system_prompt');
        if ($defaultPrompt) {
            $systemParts[] = $defaultPrompt;
        }

        // Add Smart Resolution instructions if enabled
        if (config('ai-agent.smart_resolution.enabled', false)) {
            $systemParts[] = $this->getSmartResolutionPrompt();
        }
        
        // Add security prompt (hardening)
        if (config('ai-agent.security.enabled', true)) {
            $securityPrompt = $security->getSecurityPrompt();
            if ($securityPrompt) {
                $systemParts[] = $securityPrompt;
            }
        }
        
        if (!empty($systemParts)) {
            $options['system'] = implode("\n\n---\n\n", $systemParts);
        }

        // Store user message
        $memory->remember($this->conversationId, ['role' => 'user', 'content' => $message]);

        // Run the agent loop with security checks
        $response = $this->runLoop($driver, $message, $history, $options, $security);

        // Output Sanitization - clean response
        $content = $response->content;
        if (config('ai-agent.security.enabled', true)) {
            $content = $security->sanitizeOutput($content);
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
        $driver = $this->getDriver();
        $memory = $this->getMemory();
        $history = $memory->recall($this->conversationId);

        $options = $this->options;
        if ($this->systemPrompt) {
            $options['system'] = $this->systemPrompt;
        }

        $memory->remember($this->conversationId, ['role' => 'user', 'content' => $message]);

        $response = $driver->stream($message, $this->toolRegistry->all(), $history, $onChunk);

        $memory->remember($this->conversationId, ['role' => 'assistant', 'content' => $response->content]);
    }

    /**
     * Run the agent loop (handle tool calls).
     */
    protected function runLoop(
        DriverInterface $driver,
        string $message,
        array $history,
        array $options,
        ?SecurityGuard $security = null
    ): AgentResponse {
        $maxIterations = config('ai-agent.security.max_iterations', $this->maxIterations);
        $iterations = 0;
        $tools = $this->toolRegistry->all();
        $originalMessage = $message;
        $lastToolResults = [];

        while ($iterations < $maxIterations) {
            $iterations++;

            // Check iteration limit with security
            if ($security && !$security->checkIterationLimit($iterations)) {
                return new AgentResponse(
                    content: '⚠️ تم الوصول للحد الأقصى من العمليات.',
                    toolCalls: [],
                    usage: null,
                    finishReason: 'iteration_limit'
                );
            }

            // Call the LLM
            $response = $driver->prompt($message, $tools, $history, $options);

            // If no tool calls, we're done
            if (!$response->hasToolCalls()) {
                // If response is empty but we have tool results, return the tool results
                if (empty($response->content) && !empty($lastToolResults)) {
                    $resultsText = collect($lastToolResults)
                        ->filter(fn($r) => $r['success'])
                        ->map(fn($r) => is_string($r['result']) ? $r['result'] : json_encode($r['result'], JSON_UNESCAPED_UNICODE))
                        ->join("\n");
                    
                    return new AgentResponse(
                        content: $resultsText ?: 'تم تنفيذ الأداة بنجاح.',
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

            // Execute tool calls
            $toolResults = $this->toolExecutor->executeMany($response->toolCalls, $this->context);
            $lastToolResults = $toolResults;

            // For the first iteration, add the user's original message to history
            if ($iterations === 1) {
                $history[] = [
                    'role' => 'user',
                    'content' => $originalMessage,
                ];
            }

            // Add assistant's response with tool calls to history
            $history[] = [
                'role' => 'assistant',
                'content' => $response->content,
                'tool_calls' => $response->toolCalls,
            ];

            // Add tool results to history  
            foreach ($toolResults as $result) {
                $history[] = [
                    'role' => 'tool',
                    'tool_call_id' => $result['tool_call_id'],
                    'tool_name' => $result['name'],
                    'content' => $result['success'] 
                        ? json_encode($result['result'], JSON_UNESCAPED_UNICODE)
                        : "Error: " . $result['error'],
                ];
            }

            // Continue with a message asking LLM to respond based on tool results
            $message = 'بناءً على نتيجة الأداة، أجب على سؤال المستخدم.';
            
            // Add small delay between requests for some APIs (like Gemini)
            usleep(300000); // 300ms delay
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
        }

        return $this->memory;
    }

    /**
     * Resolve memory driver by name.
     */
    protected function resolveMemory(string $name): MemoryInterface
    {
        return match ($name) {
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
        $classes = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $classes = array_merge($classes, $this->findClassesInPath($path));
            }
        }

        if (!empty($classes)) {
            $tools = $this->toolDiscovery->discover($classes);
            $this->toolRegistry->register($tools);
        }
    }

    /**
     * Find PHP classes in a directory.
     */
    protected function findClassesInPath(string $path): array
    {
        $classes = [];
        $files = glob($path . '/*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) &&
                preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                $classes[] = $nsMatch[1] . '\\' . $classMatch[1];
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
