<?php

namespace LaravelAIAgent;

use Illuminate\Support\ServiceProvider;
use LaravelAIAgent\Contracts\DriverInterface;
use LaravelAIAgent\Contracts\MemoryInterface;
use LaravelAIAgent\Drivers\OpenAIDriver;
use LaravelAIAgent\Drivers\AnthropicDriver;
use LaravelAIAgent\Memory\SessionMemory;
use LaravelAIAgent\Memory\NullMemory;
use LaravelAIAgent\Tools\ToolDiscovery;
use LaravelAIAgent\Tools\ToolRegistry;
use LaravelAIAgent\Tools\ToolExecutor;
use LaravelAIAgent\Tools\ToolValidator;
use LaravelAIAgent\Security\SecurityGuard;
use LaravelAIAgent\Security\AuditLogger;
use LaravelAIAgent\Security\ContentModerator;
use LaravelAIAgent\Security\OutputSanitizer;

class AgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Load helper functions
        require_once __DIR__ . '/helpers.php';

        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-agent.php', 'ai-agent');

        // Register AgentContext as singleton (request-scoped)
        $this->app->singleton(AgentContext::class);

        // Register the main Agent class
        $this->app->bind('ai-agent', function ($app) {
            return new Agent();
        });

        // Register Tool services
        $this->app->singleton(ToolDiscovery::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(ToolValidator::class);
        
        $this->app->singleton(ToolExecutor::class, function ($app) {
            return new ToolExecutor(
                $app->make(ToolRegistry::class),
                $app->make(ToolValidator::class)
            );
        });

        // Register Security services
        $this->app->singleton(ContentModerator::class);
        $this->app->singleton(OutputSanitizer::class);
        $this->app->singleton(SecurityGuard::class);
        $this->app->singleton(AuditLogger::class);

        // Register Memory
        $this->app->bind(MemoryInterface::class, function ($app) {
            $driver = config('ai-agent.memory.driver', 'session');
            
            return match ($driver) {
                'session' => new SessionMemory(),
                'null' => new NullMemory(),
                default => new SessionMemory(),
            };
        });

        // Register Drivers
        $this->app->bind('openai_driver', OpenAIDriver::class);
        $this->app->bind('anthropic_driver', AnthropicDriver::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/ai-agent.php' => config_path('ai-agent.php'),
        ], 'ai-agent-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ai-agent-migrations');

        // Publish widget assets
        $this->publishes([
            __DIR__ . '/../resources/js/widget' => public_path('vendor/ai-agent'),
        ], 'ai-agent-widget');

        // Load widget routes
        if (config('ai-agent.widget.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/widget.php');
        }

        // Register agent routes
        $this->registerAgentRoutes();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ChatCommand::class,
                Console\MakeAgentCommand::class,
            ]);
        }
    }

    /**
     * Register routes for each configured agent.
     */
    protected function registerAgentRoutes(): void
    {
        $agents = config('ai-agent.agents', []);

        if (empty($agents)) {
            return;
        }

        foreach ($agents as $agentName => $agentConfig) {
            \Illuminate\Support\Facades\Route::middleware($agentConfig['middleware'] ?? ['api'])
                ->prefix($agentConfig['prefix'] ?? 'ai-agent')
                ->group(function () use ($agentName) {
                    \Illuminate\Support\Facades\Route::post("/{$agentName}/chat", [Http\Controllers\ChatController::class, 'agentChat'])
                        ->name("ai-agent.{$agentName}.chat")
                        ->defaults('agent', $agentName);
                });
        }
    }
}

