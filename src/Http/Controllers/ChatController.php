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
