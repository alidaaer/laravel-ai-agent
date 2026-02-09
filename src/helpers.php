<?php

if (!function_exists('isAICall')) {
    /**
     * Check if the current execution is inside an AI tool call.
     */
    function isAICall(): bool
    {
        return app(\LaravelAIAgent\AgentContext::class)->isAICall();
    }
}
