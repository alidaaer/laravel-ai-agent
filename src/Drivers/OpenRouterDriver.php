<?php

namespace LaravelAIAgent\Drivers;

/**
 * OpenRouter Driver
 * 
 * OpenRouter provides access to multiple AI models through a single API.
 * It uses the same schema as OpenAI, making integration seamless.
 * 
 * @see https://openrouter.ai/docs
 */
class OpenRouterDriver extends OpenAIDriver
{
    protected string $siteUrl;
    protected string $siteName;

    public function __construct()
    {
        $config = config('ai-agent.drivers.openrouter');
        
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'openai/gpt-4o-mini';
        $this->baseUrl = $config['base_url'] ?? 'https://openrouter.ai/api/v1';
        $this->timeout = $config['timeout'] ?? 60;
        $this->maxRetries = $config['retry']['times'] ?? 3;
        $this->retryDelay = $config['retry']['sleep'] ?? 1000;
        
        // OpenRouter specific settings
        $this->siteUrl = $config['site_url'] ?? config('app.url', '');
        $this->siteName = $config['site_name'] ?? config('app.name', 'Laravel AI Agent');
    }

    public function getName(): string
    {
        return 'openrouter';
    }

    protected function getHeaders(): array
    {
        $headers = parent::getHeaders();
        
        // OpenRouter specific headers for attribution
        if ($this->siteUrl) {
            $headers['HTTP-Referer'] = $this->siteUrl;
        }
        if ($this->siteName) {
            $headers['X-Title'] = $this->siteName;
        }
        
        return $headers;
    }
}
