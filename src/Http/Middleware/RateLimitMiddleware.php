<?php

namespace LaravelAIAgent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Check if rate limiting is enabled
        if (!config('ai-agent.rate_limit.enabled', true)) {
            return $next($request);
        }

        $maxRequests = config('ai-agent.rate_limit.max_requests_per_minute', 60);
        
        // Determine the key: authenticated user ID or IP address
        $key = $this->resolveRequestKey($request);
        
        // Execute the rate limiter
        $executed = RateLimiter::attempt(
            key: "ai-agent:{$key}",
            maxAttempts: $maxRequests,
            callback: fn () => true,
            decaySeconds: 60
        );

        if (!$executed) {
            $secondsUntilAvailable = RateLimiter::availableIn("ai-agent:{$key}");
            
            return Response::json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $secondsUntilAvailable
            ], 429)->header('Retry-After', $secondsUntilAvailable);
        }

        return $next($request);
    }

    /**
     * Resolve the rate limit key for the request.
     */
    protected function resolveRequestKey(Request $request): string
    {
        // Use authenticated user ID if available
        if ($request->user()) {
            return 'user:' . $request->user()->getAuthIdentifier();
        }

        // Fallback to IP address
        return 'ip:' . $request->ip();
    }
}
