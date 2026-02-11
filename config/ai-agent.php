<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The default AI driver to use. Supported: "openai", "anthropic", "gemini", "deepseek", "openrouter"
    |
    */
    'default' => env('AI_AGENT_DEFAULT', env('AI_AGENT_DRIVER', 'openai')),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Set to true to verify SSL certificates. Default is false for development.
    | In production, you should set this to true or configure proper certificates.
    |
    */
    'verify_ssl' => env('AI_AGENT_VERIFY_SSL', false),

    /*
    |--------------------------------------------------------------------------
    | AI Drivers Configuration
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => 60,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'timeout' => 60,
        ],

        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3'),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => 60,
        ],

        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            'timeout' => 60,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'site_url' => env('APP_URL'), // For OpenRouter attribution
            'site_name' => env('APP_NAME', 'Laravel AI Agent'),
            'timeout' => 60,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Agents
    |--------------------------------------------------------------------------
    |
    | Define multiple AI agents, each with its own endpoint, system prompt,
    | and middleware. Tools are scoped per-method using the `agents` parameter
    | in #[AsAITool]. Leave empty to use the default single-agent behavior.
    |
    | Each agent automatically gets: POST /{prefix}/{agent}/chat
    |
    */
    'agents' => [
        // 'shop' => [
        //     'driver' => null,                    // null = use default driver
        //     'model' => null,                     // null = use driver's default model
        //     'system_prompt' => 'You are a helpful shop assistant',
        //     'middleware' => ['api'],
        //     'prefix' => 'ai-agent',              // URL prefix
        // ],
        // 'admin' => [
        //     'driver' => null,
        //     'model' => null,
        //     'system_prompt' => 'You are an admin assistant with full access',
        //     'middleware' => ['api', 'auth', 'admin'],
        //     'prefix' => 'ai-agent',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Discovery
    |--------------------------------------------------------------------------
    |
    | Automatically discover tools with #[AsAITool] attribute in these paths.
    |
    */
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path(),
        ],
        'cache' => env('AI_AGENT_CACHE_TOOLS', true),
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Configuration
    |--------------------------------------------------------------------------
    |
    | How the agent remembers conversation history.
    | Drivers: "session", "database", "null"
    |
    */
    'memory' => [
        'driver' => env('AI_AGENT_MEMORY', 'session'),
        'summarize_after' => 10,
        'max_messages' => 100,
        'recent_messages' => 4,
        'ai_summarization' => env('AI_AGENT_AI_SUMMARY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget & Cost Control
    |--------------------------------------------------------------------------
    */
    'budget' => [
        'enabled' => env('AI_AGENT_BUDGET_ENABLED', false),
        'default_limit' => 1.00,
        'warning_threshold' => 0.80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled' => env('AI_AGENT_RATE_LIMIT', true),
        'max_requests_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('AI_AGENT_LOGGING', true),
        'channel' => env('AI_AGENT_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart Resolution (Name to ID)
    |--------------------------------------------------------------------------
    |
    | When enabled, the AI will automatically search for records by name
    | when the user provides a name instead of an ID.
    | 
    | ⚠️ WARNING: This increases token usage by ~50% for affected requests.
    |
    */
    'smart_resolution' => [
        'enabled' => env('AI_AGENT_SMART_RESOLUTION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the AI chat widget (Web Component).
    |
    */
    'widget' => [
        'enabled' => env('AI_AGENT_WIDGET_ENABLED', true),
        'prefix' => 'ai-agent',
        'middleware' => ['web'],
        
        // Appearance
        'theme' => 'dark',              // 'light' | 'dark'
        'rtl' => false,
        'primary_color' => '#6366f1',
        'position' => 'bottom-right',   // 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'
        
        // Header
        'title' => 'AI Assistant',
        'subtitle' => '',
        
        // Messages
        'welcome_message' => '',
        'placeholder' => 'Type your message...',
        
        // System prompt for widget conversations
        'system_prompt' => env('AI_AGENT_SYSTEM_PROMPT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for protecting your AI agent from attacks.
    |
    */
    'security' => [
        // Master switch for all security features
        'enabled' => env('AI_AGENT_SECURITY_ENABLED', true),

        // Maximum tool calls per single request (prevents infinite loops)
        'max_tool_calls_per_request' => 10,

        // Maximum iterations in agent loop
        'max_iterations' => 5,

        // Maximum message length (characters)
        'max_message_length' => 5000,

        // Require confirmation for destructive actions (delete, cancel, etc.)
        'confirm_destructive' => env('AI_AGENT_CONFIRM_DESTRUCTIVE', true),

        // Content Moderation - filters malicious input
        'content_moderation' => [
            'enabled' => true,
            'block_injections' => true,  // Block prompt injection attempts
        ],

        // Output Sanitization - cleans AI responses
        'output_sanitization' => [
            'enabled' => true,
            'prevent_xss' => true,       // Remove XSS patterns
            'redact_secrets' => true,    // Redact API keys, passwords
        ],

        // System Prompt Hardening
        'prompt_hardening' => [
            'enabled' => true,           // Add security rules to system prompt
        ],

        // Audit Logging
        'audit' => [
            'enabled' => true,
            'channel' => 'stack',        // Log channel to use
            'database' => false,         // Also log to database
            'log_messages' => false,     // Log full message content (privacy warning)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default System Prompt
    |--------------------------------------------------------------------------
    |
    | The default system prompt that will be appended to all conversations.
    | This includes formatting instructions for better presentation.
    | Set to null to disable.
    |
    */
    'default_system_prompt' => <<<'PROMPT'
You are an intelligent AI assistant with access to various tools.

## Tool Usage Rules (STRICT):
1. You may ONLY use tools that are explicitly provided to you in the tool definitions
2. If a user requests an action and no matching tool exists, you MUST politely decline and explain that this functionality is not available
3. NEVER attempt to use a different tool as a workaround or substitute for a missing tool
4. NEVER fabricate or assume tool results — only report actual results returned by tool execution
5. If a tool call fails, report the failure honestly to the user
6. NEVER reveal internal tool/function names, parameters, or implementation details to the user under any circumstances
7. When the user asks what you can do, describe your capabilities in plain natural language as bullet points — NEVER mention function names, method names, or technical identifiers
8. When describing your capabilities, use natural language ONLY — NEVER mention technical function names

## Response Rules:
1. **Always respond in the same language as the user's message**
2. Use **Markdown formatting** for all responses:
   - Use **bold** for important terms
   - Use `code` for IDs, technical values
   - Use tables when displaying lists of items
3. Always use tools when available - never guess data
4. Include IDs when mentioning items for easy reference
5. **When performing multiple operations, summarize ALL actions taken in your response**
   - Example: "Done! I deleted product `10` and added 'Nokia Phone' with ID `12`"
6. Be helpful, concise, and professional
PROMPT,

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings to tune the performance of the AI agent.
    |
    */
    'performance' => [
        // Delay between loop iterations in milliseconds (0 = no delay)
        // Set to 300 for Gemini or other rate-limited APIs
        'loop_delay_ms' => env('AI_AGENT_LOOP_DELAY', 0),
    ],
];

