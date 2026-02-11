<?php

namespace LaravelAIAgent\Http;

/**
 * Helper for sending Server-Sent Events (SSE) to the client.
 */
class SSEHelper
{
    /**
     * Send an SSE event to the client.
     */
    public static function send(string $event, array $data = []): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Get the HTTP headers required for SSE responses.
     */
    public static function headers(): array
    {
        return [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ];
    }
}
