<?php

namespace LaravelAIAgent;

use Illuminate\Support\Str;
use LaravelAIAgent\Concerns\HasWidgetConfig;

abstract class BaseAgent
{
    use HasWidgetConfig;
    protected Agent $agent;
    protected ?string $conversationId = null;
    protected array $boundModels = [];

    /**
     * Get the instructions (system prompt) for this agent.
     */
    abstract public function instructions(): string;

    /**
     * Get the driver name. Return null to use the default driver.
     */
    public function driver(): ?string
    {
        return null;
    }

    /**
     * Get the model name. Return null to use the driver's default model.
     */
    public function model(): ?string
    {
        return null;
    }

    /**
     * Get the tool service classes for this agent.
     * Tools are auto-discovered from these classes using #[AsAITool] attribute.
     * Return empty array to use auto-discovery from config paths.
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Get the middleware pipeline for this agent.
     * Each middleware receives (AgentPrompt $prompt, Closure $next).
     *
     * @return array<class-string|object>
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Get the structured output schema.
     * Return null for free-form text responses.
     */
    public function schema(): ?array
    {
        return null;
    }

    /**
     * Get the temperature for this agent.
     */
    public function temperature(): ?float
    {
        return null;
    }

    /**
     * Get the max tokens for this agent.
     */
    public function maxTokens(): ?int
    {
        return null;
    }

    // =========================================================================
    // Routing — Override to customize how this agent is exposed via HTTP
    // =========================================================================

    /**
     * Get the route name (URL slug) for this agent.
     * Default: kebab-case of class name without "Agent" suffix.
     * Example: ShopAssistant → shop-assistant
     */
    public static function routeName(): string
    {
        $class = class_basename(static::class);
        $class = preg_replace('/(Agent|Assistant)$/i', '', $class);

        return Str::kebab($class);
    }

    /**
     * Get the route middleware for this agent.
     */
    public function routeMiddleware(): array
    {
        return ['web'];
    }

    /**
     * Get the route prefix for this agent.
     */
    public function routePrefix(): string
    {
        return config('ai-agent.widget.prefix', 'ai-agent');
    }

    // =========================================================================
    // Static API — The Simple Way
    // =========================================================================

    /**
     * Send a chat message using this agent.
     *
     * Usage: ShopAssistant::chat('list products');
     */
    public static function chat(string $message): string
    {
        return (new static)->send($message);
    }

    /**
     * Send a chat message with SSE events.
     *
     * Usage: ShopAssistant::chatWithEvents('list products', fn($e, $d) => ...);
     */
    public static function chatWithEvents(string $message, callable $onEvent): string
    {
        return (new static)->sendWithEvents($message, $onEvent);
    }

    /**
     * Bind Eloquent models as context.
     *
     * Usage: ShopAssistant::for($user, $order)->chat('status?');
     */
    public static function for(...$models): static
    {
        $instance = new static;
        $instance->boundModels = $models;
        return $instance;
    }

    /**
     * Continue an existing conversation.
     *
     * Usage: ShopAssistant::conversation($id)->chat('follow up');
     */
    public static function conversation(string $id): static
    {
        $instance = new static;
        $instance->conversationId = $id;
        return $instance;
    }

    // =========================================================================
    // Instance API — For Chained Calls
    // =========================================================================

    /**
     * Send a message (instance method).
     */
    public function send(string $message): string
    {
        return $this->buildAgent()->chat($message);
    }

    /**
     * Send a message with SSE events (instance method).
     */
    public function sendWithEvents(string $message, callable $onEvent): string
    {
        return $this->buildAgent()->chatWithEvents($message, $onEvent);
    }

    /**
     * Set conversation ID on instance.
     */
    public function withConversation(string $id): static
    {
        $this->conversationId = $id;
        return $this;
    }

    /**
     * Bind models on instance.
     */
    public function withContext(...$models): static
    {
        $this->boundModels = array_merge($this->boundModels, $models);
        return $this;
    }

    // =========================================================================
    // Internal: Build the Agent instance
    // =========================================================================

    /**
     * Build and configure the underlying Agent instance.
     */
    protected function buildAgent(): Agent
    {
        // Skip auto-discovery — we'll handle it via agentScope below
        $tools = $this->tools();
        $agent = new Agent(class_basename(static::class), skipAutoDiscovery: true);

        // Scope tools to this agent's route name (filters by #[AsAITool(agents: [...])])
        $agent->agentScope(static::routeName());

        // Set driver
        $driverName = $this->driver();
        if ($driverName) {
            $agent->driver($driverName);
        }

        // Set model
        $modelName = $this->model();
        if ($modelName) {
            $agent->model($modelName);
        }

        // Set system prompt
        $agent->system($this->instructions());

        // Set explicit tools (overrides auto-discovered if provided)
        if (!empty($tools)) {
            $agent->tools($tools);
        }

        // Set temperature
        $temp = $this->temperature();
        if ($temp !== null) {
            $agent->temperature($temp);
        }

        // Set max tokens
        $maxTokens = $this->maxTokens();
        if ($maxTokens !== null) {
            $agent->maxTokens($maxTokens);
        }

        // Set conversation ID
        if ($this->conversationId) {
            $agent->conversation($this->conversationId);
        }

        // Bind models as context
        if (!empty($this->boundModels)) {
            $agent->for(...$this->boundModels);
        }

        // Set middleware pipeline
        $middleware = $this->middleware();
        if (!empty($middleware)) {
            $agent->withMiddleware($middleware);
        }

        // Set structured output schema
        $schema = $this->schema();
        if ($schema !== null) {
            $agent->structured($schema);
        }

        // Store for reference
        $this->agent = $agent;

        return $agent;
    }

    /**
     * Get the underlying Agent instance (after build).
     */
    public function getAgent(): Agent
    {
        return $this->agent ?? $this->buildAgent();
    }

    /**
     * Get the conversation ID.
     */
    public function getConversationId(): string
    {
        return $this->conversationId ?? $this->getAgent()->getConversationId();
    }
}
