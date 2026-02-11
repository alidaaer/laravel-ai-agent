<?php

namespace LaravelAIAgent\Drivers;

/**
 * DeepSeek Driver
 * 
 * DeepSeek API is fully OpenAI-compatible, so we extend OpenAIDriver
 * and only override the constructor and name.
 * 
 * Supports: deepseek-chat, deepseek-reasoner (R1)
 */
class DeepSeekDriver extends OpenAIDriver
{
    public function __construct()
    {
        $config = config('ai-agent.drivers.deepseek');
        
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'deepseek-chat';
        $this->baseUrl = $config['base_url'] ?? 'https://api.deepseek.com/v1';
        $this->timeout = $config['timeout'] ?? 60;
        $this->maxRetries = $config['retry']['times'] ?? 3;
        $this->retryDelay = $config['retry']['sleep'] ?? 1000;
    }

    public function getName(): string
    {
        return 'deepseek';
    }
}
