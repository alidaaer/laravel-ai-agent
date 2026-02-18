<?php

namespace LaravelAIAgent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelAIAgent\Facades\Agent;

class ChatController extends Controller
{
    // =========================================================================
    // Widget Endpoints (default single-agent)
    // =========================================================================

    /**
     * Handle chat message from widget.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|string|max:100',
        ]);

        try {
            $conversationId = $request->input('conversation_id', session()->getId());

            $response = Agent::driver(config('ai-agent.default'))
                ->conversation($conversationId)
                ->system(config('ai-agent.widget.system_prompt', 'You are a helpful AI assistant.'))
                ->chat($request->input('message'));

            return response()->json([
                'success' => true,
                'response' => $response,
                'conversation_id' => $conversationId,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e);
        }
    }

    /**
     * Handle chat message with SSE streaming events.
     */
    public function chatStream(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|string|max:100',
        ]);

        $message = $request->input('message');
        $conversationId = $request->input('conversation_id', session()->getId());

        return response()->stream(function () use ($message, $conversationId) {
            $onEvent = function (string $event, array $data = []) {
                \LaravelAIAgent\Http\SSEHelper::send($event, $data);
            };

            try {
                Agent::driver(config('ai-agent.default'))
                    ->conversation($conversationId)
                    ->system(config('ai-agent.widget.system_prompt') ?? 'You are a helpful AI assistant.')
                    ->chatWithEvents($message, $onEvent);
            } catch (\Throwable $e) {
                $this->sseError($e, $onEvent);
            }
        }, 200, \LaravelAIAgent\Http\SSEHelper::headers());
    }

    // =========================================================================
    // Named Agent Endpoints (multi-agent via config)
    // =========================================================================

    /**
     * Handle chat message for a named agent.
     */
    public function agentChat(Request $request, string $agent)
    {
        $agentClass = $this->findAgentClass($agent);

        if (!$agentClass) {
            return response()->json([
                'success' => false,
                'error' => "Agent '{$agent}' not configured.",
            ], 404);
        }

        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|string|max:100',
        ]);

        try {
            $conversationId = $request->input('conversation_id', session()->getId());
            $response = $this->resolveAgent($agentClass, $conversationId)
                ->chat($request->input('message'));

            return response()->json([
                'success' => true,
                'response' => $response,
                'conversation_id' => $conversationId,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e);
        }
    }

    /**
     * Handle chat message with SSE streaming for a named agent.
     */
    public function agentChatStream(Request $request, string $agent)
    {
        $agentClass = $this->findAgentClass($agent);

        if (!$agentClass) {
            return response()->json([
                'success' => false,
                'error' => "Agent '{$agent}' not configured.",
            ], 404);
        }

        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|string|max:100',
        ]);

        $message = $request->input('message');
        $conversationId = $request->input('conversation_id', session()->getId());

        return response()->stream(function () use ($message, $conversationId, $agentClass) {
            $onEvent = function (string $event, array $data = []) {
                \LaravelAIAgent\Http\SSEHelper::send($event, $data);
            };

            try {
                $this->resolveAgent($agentClass, $conversationId)
                    ->chatWithEvents($message, $onEvent);
            } catch (\Throwable $e) {
                $this->sseError($e, $onEvent);
            }
        }, 200, \LaravelAIAgent\Http\SSEHelper::headers());
    }

    // =========================================================================
    // Agent Resolution
    // =========================================================================

    /**
     * Find the agent class registered for a given route name.
     *
     * @return class-string<\LaravelAIAgent\BaseAgent>|null
     */
    protected function findAgentClass(string $routeName): ?string
    {
        $agents = config('ai-agent.agents', []);

        foreach ($agents as $agentClass) {
            if (is_string($agentClass)
                && is_subclass_of($agentClass, \LaravelAIAgent\BaseAgent::class)
                && $agentClass::routeName() === $routeName
            ) {
                return $agentClass;
            }
        }

        return null;
    }

    /**
     * Resolve an agent class into an Agent instance ready to chat.
     */
    protected function resolveAgent(string $agentClass, string $conversationId): \LaravelAIAgent\Agent
    {
        return (new $agentClass)
            ->withConversation($conversationId)
            ->getAgent();
    }

    // =========================================================================
    // Error Handling
    // =========================================================================

    /**
     * Format an exception as a JSON error response.
     */
    protected function jsonError(\Throwable $e): \Illuminate\Http\JsonResponse
    {
        $message = $this->formatErrorMessage($e);

        return response()->json([
            'success' => false,
            'error' => $message,
        ], 500);
    }

    /**
     * Send an error via SSE.
     */
    protected function sseError(\Throwable $e, callable $onEvent): void
    {
        $onEvent('error', ['message' => $this->formatErrorMessage($e)]);
    }

    /**
     * Format a user-friendly error message from an exception.
     */
    protected function formatErrorMessage(\Throwable $e): string
    {
        if ($e instanceof \LaravelAIAgent\Exceptions\DriverException) {
            return match ($e->getHttpCode()) {
                429 => 'Rate limit exceeded. Please try again later.',
                401 => 'Authentication failed. Please check your API configuration.',
                403 => 'Access denied. Please verify your API permissions.',
                default => config('app.debug') ? $e->getMessage() : 'An error occurred.',
            };
        }

        return config('app.debug') ? $e->getMessage() : 'An error occurred';
    }

    /**
     * Get conversation history for the widget.
     * Returns only user/assistant messages (no tool calls).
     */
    public function history(Request $request)
    {
        $conversationId = $request->query('conversation_id', session()->getId());

        try {
            $memory = \LaravelAIAgent\Agent::resolveMemory(config('ai-agent.memory.driver', 'session'));
            $messages = $memory->recall($conversationId);

            // Filter: only user and assistant messages for display
            $filtered = array_values(array_filter($messages, function ($msg) {
                return in_array($msg['role'], ['user', 'assistant']);
            }));
            // dd($filtered);
            // Ensure all messages have metadata array
            // $filtered = array_map(function ($msg) {
            //     $msg['metadata'] = $msg['metadata'] ?? [];
            //     return $msg;
            // }, $filtered);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'messages' => $filtered,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to load history',
            ], 500);
        }
    }

    /**
     * List all conversations with metadata.
     */
    public function conversations(Request $request)
    {
        try {
            $memory = \LaravelAIAgent\Agent::resolveMemory(config('ai-agent.memory.driver', 'session'));
            $conversations = $memory->conversationsWithMeta();

            return response()->json([
                'success' => true,
                'conversations' => $conversations,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to load conversations',
            ], 500);
        }
    }

    /**
     * Clear conversation history.
     */
    public function clear(Request $request)
    {
        $conversationId = $request->input('conversation_id', session()->getId());

        try {
            $memory = \LaravelAIAgent\Agent::resolveMemory(config('ai-agent.memory.driver', 'session'));
            $memory->forget($conversationId);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to clear history',
            ], 500);
        }
    }

    // =========================================================================
    // Agent-Scoped Conversations (isolated per agent)
    // =========================================================================

    /**
     * List conversations for a specific agent.
     */
    public function agentConversations(Request $request, string $agent)
    {
        try {
            $memory = \LaravelAIAgent\Agent::resolveMemory(config('ai-agent.memory.driver', 'session'));
            $memory->forAgent($agent);
            $conversations = $memory->conversationsWithMeta();

            return response()->json([
                'success' => true,
                'conversations' => $conversations,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to load conversations',
            ], 500);
        }
    }

    /**
     * Get conversation history for a specific agent's widget.
     */
    public function agentHistory(Request $request, string $agent)
    {
        $conversationId = $request->query('conversation_id', session()->getId());

        try {
            $memory = \LaravelAIAgent\Agent::resolveMemory(config('ai-agent.memory.driver', 'session'));
            $memory->forAgent($agent);
            $messages = $memory->recall($conversationId);

            $filtered = array_values(array_filter($messages, function ($msg) {
                return in_array($msg['role'], ['user', 'assistant']);
            }));

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'messages' => $filtered,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to load history',
            ], 500);
        }
    }

    /**
     * Clear conversation history for a specific agent.
     */
    public function agentClear(Request $request, string $agent)
    {
        $conversationId = $request->input('conversation_id', session()->getId());

        try {
            $memory = \LaravelAIAgent\Agent::resolveMemory(config('ai-agent.memory.driver', 'session'));
            $memory->forAgent($agent);
            $memory->forget($conversationId);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to clear history',
            ], 500);
        }
    }

    // =========================================================================
    // Widget Assets
    // =========================================================================

    /**
     * Serve the widget JavaScript.
     */
    public function widget()
    {
        // Get the package root directory
        $packageRoot = dirname(__DIR__, 3); // Go up 3 levels from Controllers
        $path = $packageRoot . '/resources/js/widget/ai-agent-chat.js';
        
        if (!file_exists($path)) {
            // Fallback: try published path
            $publishedPath = public_path('vendor/ai-agent/ai-agent-chat.js');
            if (file_exists($publishedPath)) {
                $path = $publishedPath;
            } else {
                abort(404, 'Widget not found. Run: php artisan vendor:publish --tag=ai-agent-widget');
            }
        }
        
        return response(file_get_contents($path))
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Get widget configuration.
     */
    public function config()
    {
        return response()->json([
            'endpoint' => route('ai-agent.chat'),
            'theme' => config('ai-agent.widget.theme', 'dark'),
            'rtl' => config('ai-agent.widget.rtl', false),
            'title' => config('ai-agent.widget.title', 'AI Assistant'),
            'subtitle' => config('ai-agent.widget.subtitle', ''),
            'welcome_message' => config('ai-agent.widget.welcome_message', ''),
            'placeholder' => config('ai-agent.widget.placeholder', 'Type your message...'),
            'primary_color' => config('ai-agent.widget.primary_color', '#6366f1'),
        ]);
    }
}
