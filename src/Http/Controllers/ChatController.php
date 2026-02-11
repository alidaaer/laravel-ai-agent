<?php

namespace LaravelAIAgent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelAIAgent\Facades\Agent;

class ChatController extends Controller
{
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
        } catch (\LaravelAIAgent\Exceptions\DriverException $e) {
            // Handle specific driver errors with user-friendly messages
            $statusCode = $e->getHttpCode();
            $message = match ($statusCode) {
                429 => 'Rate limit exceeded. You have reached the maximum number of requests. Please try again later.',
                401 => 'Authentication failed. Please check your API configuration.',
                403 => 'Access denied. Please verify your API permissions.',
                default => config('app.debug') ? $e->getMessage() : 'An error occurred while processing your request.',
            };
            
            return response()->json([
                'success' => false,
                'error' => $message,
            ], $statusCode >= 400 && $statusCode < 600 ? 500 : 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Handle chat message with SSE streaming events.
     * Sends real-time progress events (thinking, tool_start, tool_done, done).
     */
    public function chatStream(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|string|max:100',
        ]);

        $message = $request->input('message');
        $conversationId = $request->input('conversation_id', session()->getId());

        return response()->stream(function () use ($message, $conversationId, $request) {
            $onEvent = function (string $event, array $data = []) {
                \LaravelAIAgent\Http\SSEHelper::send($event, $data);
            };

            try {
                $agent = Agent::driver(config('ai-agent.default'))
                    ->conversation($conversationId)
                    ->system(config('ai-agent.widget.system_prompt') ?? 'You are a helpful AI assistant.');

                $agent->chatWithEvents($message, $onEvent);

            } catch (\LaravelAIAgent\Exceptions\DriverException $e) {
                $statusCode = $e->getHttpCode();
                $errorMsg = match ($statusCode) {
                    429 => 'Rate limit exceeded. Please try again later.',
                    401 => 'Authentication failed.',
                    403 => 'Access denied.',
                    default => config('app.debug') ? $e->getMessage() : 'An error occurred.',
                };
                $onEvent('error', ['message' => $errorMsg]);
            } catch (\Throwable $e) {
                $onEvent('error', [
                    'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
                ]);
            }
        }, 200, \LaravelAIAgent\Http\SSEHelper::headers());
    }

    /**
     * Handle chat message for a specific agent.
     */
    public function agentChat(Request $request, string $agent)
    {
        $config = config("ai-agent.agents.{$agent}");

        if (!$config) {
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

            $agentInstance = new \LaravelAIAgent\Agent($agent, skipAutoDiscovery: true);
            $agentInstance->driver($config['driver'] ?? config('ai-agent.default'))
                ->conversation($conversationId)
                ->system($config['system_prompt'] ?? 'You are a helpful AI assistant.')
                ->agentScope($agent);

            if (!empty($config['model'])) {
                $agentInstance->model($config['model']);
            }

            $response = $agentInstance->chat($request->input('message'));

            return response()->json([
                'success' => true,
                'response' => $response,
                'conversation_id' => $conversationId,
            ]);
        } catch (\LaravelAIAgent\Exceptions\DriverException $e) {
            $statusCode = $e->getHttpCode();
            $message = match ($statusCode) {
                429 => 'Rate limit exceeded. Please try again later.',
                401 => 'Authentication failed. Please check your API configuration.',
                403 => 'Access denied. Please verify your API permissions.',
                default => config('app.debug') ? $e->getMessage() : 'An error occurred while processing your request.',
            };

            return response()->json([
                'success' => false,
                'error' => $message,
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
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
