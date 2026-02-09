<?php

namespace LaravelAIAgent\Drivers;

use LaravelAIAgent\AgentResponse;

class AnthropicDriver extends AbstractDriver
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $apiVersion = '2023-06-01';

    public function __construct()
    {
        $config = config('ai-agent.drivers.anthropic');
        
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'claude-3-5-sonnet-20241022';
        $this->baseUrl = $config['base_url'] ?? 'https://api.anthropic.com/v1';
        $this->timeout = $config['timeout'] ?? 60;
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function prompt(
        string $message,
        array $tools = [],
        array $history = [],
        array $options = []
    ): AgentResponse {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => $this->buildMessages($message, $history),
        ];

        // Add system prompt
        if (!empty($options['system'])) {
            $payload['system'] = $options['system'];
        }

        // Add tools if provided
        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        // Add temperature
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $response = $this->request(
            $this->baseUrl . '/messages',
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
        $payload = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => $this->buildMessages($message, $history),
            'stream' => true,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $fullContent = '';

        $response = \Illuminate\Support\Facades\Http::timeout($this->timeout)
            ->withHeaders($this->getHeaders())
            ->withOptions(['stream' => true])
            ->post($this->baseUrl . '/messages', $payload);

        foreach (explode("\n", $response->body()) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $data = json_decode(substr($line, 6), true);
                
                if (isset($data['delta']['text'])) {
                    $chunk = $data['delta']['text'];
                    $fullContent .= $chunk;
                    
                    if ($onChunk) {
                        $onChunk($chunk);
                    }
                }
            }
        }

        return new AgentResponse($fullContent, [], []);
    }

    protected function getHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ];
    }

    protected function buildMessages(string $message, array $history): array
    {
        $messages = [];

        // Add history (skip system messages as they go separately)
        foreach ($history as $msg) {
            if ($msg['role'] !== 'system') {
                $messages[] = [
                    'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $msg['content'],
                ];
            }
        }

        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    protected function formatTools(array $tools): array
    {
        $formatted = [];

        foreach ($tools as $tool) {
            $formatted[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['schema'],
            ];
        }

        return $formatted;
    }

    protected function parseResponse(array $response): AgentResponse
    {
        $content = '';
        $toolCalls = [];

        // Parse content blocks
        foreach ($response['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'arguments' => $block['input'] ?? [],
                ];
            }
        }

        // Store usage
        $this->lastUsage = [
            'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
        ];

        return new AgentResponse(
            content: $content,
            toolCalls: $toolCalls,
            usage: $this->lastUsage,
            finishReason: $response['stop_reason'] ?? null,
        );
    }
}
