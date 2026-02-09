<?php

namespace LaravelAIAgent\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \LaravelAIAgent\Agent make(string $name = 'Agent')
 * @method static \LaravelAIAgent\Agent driver(string $driver)
 * @method static \LaravelAIAgent\Agent model(string $model)
 * @method static \LaravelAIAgent\Agent system(string $prompt)
 * @method static \LaravelAIAgent\Agent tools(array $classes)
 * @method static \LaravelAIAgent\Agent withoutTools()
 * @method static \LaravelAIAgent\Agent for(...$models)
 * @method static \LaravelAIAgent\Agent withContext(array $context)
 * @method static \LaravelAIAgent\Agent conversation(string $id)
 * @method static \LaravelAIAgent\Agent temperature(float $temp)
 * @method static \LaravelAIAgent\Agent maxTokens(int $tokens)
 * @method static string chat(string $message)
 * @method static void stream(string $message, callable $onChunk)
 * @method static \LaravelAIAgent\Agent forget()
 * @method static string getConversationId()
 * 
 * @see \LaravelAIAgent\Agent
 */
class Agent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-agent';
    }

    /**
     * Create an agent instance from config by name.
     */
    public static function agent(string $name): \LaravelAIAgent\Agent
    {
        $config = config("ai-agent.agents.{$name}", []);

        $instance = new \LaravelAIAgent\Agent($name, skipAutoDiscovery: true);
        $instance->driver($config['driver'] ?? config('ai-agent.default'))
            ->system($config['system_prompt'] ?? '')
            ->agentScope($name);

        if (!empty($config['model'])) {
            $instance->model($config['model']);
        }

        return $instance;
    }

    /**
     * Check if the current execution is inside an AI tool call.
     */
    public static function isAICall(): bool
    {
        return app(\LaravelAIAgent\AgentContext::class)->isAICall();
    }

    /**
     * Get the name of the current tool being executed.
     */
    public static function currentTool(): ?string
    {
        return app(\LaravelAIAgent\AgentContext::class)->currentTool();
    }
}
