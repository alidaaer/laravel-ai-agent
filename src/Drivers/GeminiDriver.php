<?php

namespace LaravelAIAgent\Drivers;

use LaravelAIAgent\AgentResponse;

class GeminiDriver extends AbstractDriver
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $config = config('ai-agent.drivers.gemini');
        
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gemini-2.5-flash-lite';
        $this->baseUrl = $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';
        $this->timeout = $config['timeout'] ?? 60;
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function prompt(
        string $message,
        array $tools = [],
        array $history = [],
        array $options = []
    ): AgentResponse {
        $contents = $this->buildContents($message, $history, $options);
        
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 1.0,
                'maxOutputTokens' => $options['max_tokens'] ?? 8192,
            ],
        ];

        // Add system instruction if provided
        if (!empty($options['system'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $options['system']]],
            ];
        }

        // Add tools if provided
        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = $this->request($url, $payload, $this->getHeaders());

        return $this->parseResponse($response);
    }

    public function stream(
        string $message,
        array $tools = [],
        array $history = [],
        callable $onChunk = null
    ): AgentResponse {
        $contents = $this->buildContents($message, $history, []);
        
        $payload = [
            'contents' => $contents,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $url = "{$this->baseUrl}/models/{$this->model}:streamGenerateContent?key={$this->apiKey}";

        $fullContent = '';

        $response = \Illuminate\Support\Facades\Http::timeout($this->timeout)
            ->withHeaders($this->getHeaders())
            ->withOptions(['stream' => true])
            ->post($url, $payload);

        // Parse streaming response
        $buffer = '';
        foreach (explode("\n", $response->body()) as $line) {
            $buffer .= $line;
            
            // Try to parse complete JSON objects
            if (preg_match('/\{.*\}/s', $buffer, $matches)) {
                $data = json_decode($matches[0], true);
                
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $chunk = $data['candidates'][0]['content']['parts'][0]['text'];
                    $fullContent .= $chunk;
                    
                    if ($onChunk) {
                        $onChunk($chunk);
                    }
                }
                
                $buffer = '';
            }
        }

        return new AgentResponse($fullContent, [], []);
    }

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function buildContents(string $message, array $history, array $options): array
    {
        $contents = [];
        $pendingFunctionResponses = [];

        // Add history
        foreach ($history as $i => $msg) {
            // Collect function responses to attach after model's function call
            if ($msg['role'] === 'tool') {
                $pendingFunctionResponses[] = [
                    'functionResponse' => [
                        'name' => $msg['tool_name'] ?? 'tool',
                        'response' => ['result' => $msg['content']],
                    ],
                ];
                continue;
            }
            
            // If we have pending function responses and this is not a tool message,
            // add them as a single user turn
            if (!empty($pendingFunctionResponses)) {
                $contents[] = [
                    'role' => 'user',
                    'parts' => $pendingFunctionResponses,
                ];
                $pendingFunctionResponses = [];
            }
            
            // Handle assistant messages with tool calls
            if ($msg['role'] === 'assistant' && !empty($msg['tool_calls'])) {
                $parts = [];
                if (!empty($msg['content'])) {
                    $parts[] = ['text' => $msg['content']];
                }
                foreach ($msg['tool_calls'] as $toolCall) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $toolCall['name'],
                            'args' => (object)($toolCall['arguments'] ?? []),
                        ],
                    ];
                }
                $contents[] = [
                    'role' => 'model',
                    'parts' => $parts,
                ];
            }
            // Handle regular messages
            else {
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $content = $msg['content'] ?? '';
                if (!empty($content)) {
                    $contents[] = [
                        'role' => $role,
                        'parts' => [['text' => $content]],
                    ];
                }
            }
        }

        // Add any remaining function responses
        if (!empty($pendingFunctionResponses)) {
            $contents[] = [
                'role' => 'user',
                'parts' => $pendingFunctionResponses,
            ];
        }

        // Add current message only if not empty
        if (!empty($message)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $message]],
            ];
        }

        return $contents;
    }

    protected function formatTools(array $tools): array
    {
        $functionDeclarations = [];

        foreach ($tools as $tool) {
            $schema = $tool['schema'] ?? [];
            
            // Convert schema to Gemini format
            $parameters = $this->convertSchemaToGemini($schema);
            
            $declaration = [
                'name' => $tool['name'],
                'description' => $tool['description'],
            ];
            
            // Only add parameters if there are properties
            if (!empty($parameters['properties'])) {
                $declaration['parameters'] = $parameters;
            }
            
            $functionDeclarations[] = $declaration;
        }

        return [
            ['functionDeclarations' => $functionDeclarations],
        ];
    }

    /**
     * Convert JSON Schema to Gemini-compatible format.
     */
    protected function convertSchemaToGemini(array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        
        // If properties is an empty array, convert to empty object
        if (empty($properties) || $properties === []) {
            return [
                'type' => 'OBJECT',
                'properties' => new \stdClass(), // Empty object for Gemini
            ];
        }
        
        $result = [
            'type' => 'OBJECT',
            'properties' => [],
            'required' => $schema['required'] ?? [],
        ];
        
        foreach ($properties as $name => $prop) {
            $type = $prop['type'] ?? 'string';
            $geminiProp = [
                'type' => $this->mapTypeToGemini($type),
                'description' => $prop['description'] ?? ucfirst($name),
            ];
            
            // Handle array type - add items definition
            if (strtolower($type) === 'array') {
                $geminiProp['items'] = [
                    'type' => 'OBJECT',
                    'properties' => new \stdClass(),
                ];
            }
            
            // Add enum if present
            if (isset($prop['enum'])) {
                $geminiProp['enum'] = $prop['enum'];
            }
            
            $result['properties'][$name] = $geminiProp;
        }

        return $result;
    }

    /**
     * Map JSON Schema types to Gemini types.
     */
    protected function mapTypeToGemini(string $type): string
    {
        return match (strtolower($type)) {
            'string' => 'STRING',
            'integer', 'int' => 'INTEGER',
            'number', 'float', 'double' => 'NUMBER',
            'boolean', 'bool' => 'BOOLEAN',
            'array' => 'ARRAY',
            'object' => 'OBJECT',
            default => 'STRING',
        };
    }

    protected function parseResponse(array $response): AgentResponse
    {
        // Log raw response for debugging
        \Illuminate\Support\Facades\Log::debug('Gemini Raw Response', ['response' => $response]);
        
        $candidate = $response['candidates'][0] ?? [];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $textContent = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textContent .= $part['text'];
            }
            
            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => uniqid('call_'),
                    'name' => $part['functionCall']['name'],
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        // Store usage
        $this->lastUsage = [
            'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
            'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
        ];
        
        \Illuminate\Support\Facades\Log::debug('Gemini Parsed', [
            'text' => $textContent,
            'toolCalls' => $toolCalls,
            'finishReason' => $candidate['finishReason'] ?? null,
        ]);

        return new AgentResponse(
            content: $textContent,
            toolCalls: $toolCalls,
            usage: $this->lastUsage,
            finishReason: $candidate['finishReason'] ?? null,
        );
    }
}
