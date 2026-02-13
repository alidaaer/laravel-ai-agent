<?php

namespace LaravelAIAgent\Drivers;

use LaravelAIAgent\AgentResponse;

class OpenAIDriver extends AbstractDriver
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $config = config('ai-agent.drivers.openai');
        
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1';
        $this->timeout = $config['timeout'] ?? 60;
        $this->maxRetries = $config['retry']['times'] ?? 3;
        $this->retryDelay = $config['retry']['sleep'] ?? 1000;
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function prompt(
        string $message,
        array $tools = [],
        array $history = [],
        array $options = []
    ): AgentResponse {
        $messages = $this->buildMessages($message, $history, $options);
        
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        // Add tools if provided
        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
            $payload['tool_choice'] = 'auto';
        }

        // Add optional parameters
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $response = $this->request(
            $this->baseUrl . '/chat/completions',
            $payload,
            $this->getHeaders()
        );

        return $this->parseResponse($response);
    }

    public function stream(
        string $message,
        array $tools = [],
        array $history = [],
        callable $onChunk = null
    ): AgentResponse {
        $messages = $this->buildMessages($message, $history, []);
        
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $fullContent = '';
        $toolCalls = [];

        $response = \Illuminate\Support\Facades\Http::timeout($this->timeout)
            ->withHeaders($this->getHeaders())
            ->withOptions(['stream' => true])
            ->post($this->baseUrl . '/chat/completions', $payload);

        foreach (explode("\n", $response->body()) as $line) {
            if (str_starts_with($line, 'data: ') && $line !== 'data: [DONE]') {
                $data = json_decode(substr($line, 6), true);
                
                if (isset($data['choices'][0]['delta']['content'])) {
                    $chunk = $data['choices'][0]['delta']['content'];
                    $fullContent .= $chunk;
                    
                    if ($onChunk) {
                        $onChunk($chunk);
                    }
                }
            }
        }

        return new AgentResponse($fullContent, $toolCalls, []);
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    protected function buildMessages(string $message, array $history, array $options): array
    {
        $messages = [];

        // Add system prompt if provided
        if (!empty($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => $options['system']];
        }

        // Sanitize history: remove orphaned tool messages (tool without preceding assistant+tool_calls)
        $history = $this->sanitizeHistory($history);

        // Add history with proper tool_calls formatting
        foreach ($history as $msg) {
            $formattedMsg = [
                'role' => $msg['role'],
                'content' => $msg['content'] ?? '',
            ];
            
            // Format tool_calls for OpenAI/OpenRouter compatibility
            if (!empty($msg['tool_calls'])) {
                $formattedMsg['tool_calls'] = array_map(function ($tc) {
                    return [
                        'id' => $tc['id'] ?? uniqid('call_'),
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => is_array($tc['arguments']) 
                                ? json_encode($tc['arguments'], JSON_UNESCAPED_UNICODE) 
                                : $tc['arguments'],
                        ],
                    ];
                }, $msg['tool_calls']);
            }
            
            // Add tool_call_id for tool role messages
            if ($msg['role'] === 'tool' && isset($msg['tool_call_id'])) {
                $formattedMsg['tool_call_id'] = $msg['tool_call_id'];
            }
            
            $messages[] = $formattedMsg;
        }

        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    protected function formatTools(array $tools): array
    {
        $formatted = [];

        foreach ($tools as $tool) {
            $schema = $tool['schema'];

            // Ensure schema is a valid JSON Schema object
            if (empty($schema) || !isset($schema['type'])) {
                $schema = ['type' => 'object', 'properties' => new \stdClass()];
            }

            // Clean properties: keep only valid JSON Schema fields
            if (isset($schema['properties']) && is_array($schema['properties'])) {
                if (empty($schema['properties'])) {
                    $schema['properties'] = new \stdClass();
                } else {
                    $validKeys = ['type', 'description', 'enum', 'items', 'properties', 'default'];
                    foreach ($schema['properties'] as $propName => &$propDef) {
                        if (is_array($propDef)) {
                            $propDef = array_intersect_key($propDef, array_flip($validKeys));
                        }
                    }
                    unset($propDef);
                }
            }

            $formatted[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $schema,
                ],
            ];
        }

        return $formatted;
    }

    /**
     * Sanitize history for strict API compatibility (DeepSeek, etc).
     * - Remove orphaned tool messages (no preceding assistant with tool_calls)
     * - Remove assistant+tool_calls if not ALL tool responses are present
     */
    protected function sanitizeHistory(array $history): array
    {
        // Pass 1: collect all tool_call_ids that have tool responses
        $respondedToolCallIds = [];
        foreach ($history as $msg) {
            if (($msg['role'] ?? '') === 'tool' && isset($msg['tool_call_id'])) {
                $respondedToolCallIds[$msg['tool_call_id']] = true;
            }
        }

        // Pass 2: build clean history
        $clean = [];
        $skipToolCallIds = [];
        $validToolCallIds = [];

        foreach ($history as $msg) {
            $role = $msg['role'] ?? '';

            if ($role === 'assistant' && !empty($msg['tool_calls'])) {
                // Check if ALL tool_calls have matching tool responses
                $allSatisfied = true;
                foreach ($msg['tool_calls'] as $tc) {
                    $tcId = $tc['id'] ?? ($tc['tool_call_id'] ?? null);
                    if ($tcId && !isset($respondedToolCallIds[$tcId])) {
                        $allSatisfied = false;
                        break;
                    }
                }

                if ($allSatisfied) {
                    $clean[] = $msg;
                    // Track valid tool_call_ids for O(1) lookup
                    foreach ($msg['tool_calls'] as $tc) {
                        $tcId = $tc['id'] ?? ($tc['tool_call_id'] ?? null);
                        if ($tcId) {
                            $validToolCallIds[$tcId] = true;
                        }
                    }
                } else {
                    // Mark these tool_call_ids to skip their partial responses too
                    foreach ($msg['tool_calls'] as $tc) {
                        $tcId = $tc['id'] ?? ($tc['tool_call_id'] ?? null);
                        if ($tcId) {
                            $skipToolCallIds[$tcId] = true;
                        }
                    }
                    // Add assistant without tool_calls (keep the text if any)
                    $content = $msg['content'] ?? '';
                    if (!empty($content)) {
                        $clean[] = ['role' => 'assistant', 'content' => $content];
                    }
                }
                continue;
            }

            if ($role === 'tool') {
                $tcId = $msg['tool_call_id'] ?? null;
                // Skip if orphaned or belongs to a dropped assistant
                if ($tcId && isset($skipToolCallIds[$tcId])) {
                    continue;
                }
                // O(1) lookup instead of O(n) reverse scan
                if ($tcId && isset($validToolCallIds[$tcId])) {
                    $clean[] = $msg;
                }
                continue;
            }

            $clean[] = $msg;
        }

        return $clean;
    }

    protected function parseResponse(array $response): AgentResponse
    {
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        // Store usage
        $this->lastUsage = $response['usage'] ?? [];

        // Parse tool calls if present
        $toolCalls = [];
        if (!empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $toolCalls[] = [
                    'id' => $tc['id'],
                    'name' => $tc['function']['name'],
                    'arguments' => json_decode($tc['function']['arguments'], true) ?? [],
                ];
            }
        }

        return new AgentResponse(
            content: $message['content'] ?? '',
            toolCalls: $toolCalls,
            usage: $this->lastUsage,
            finishReason: $choice['finish_reason'] ?? null,
        );
    }
}
