<p align="center">
  <img src="logo.png" width="300" alt="Laravel AI Agent Logo">
</p>

<h1 align="center">Laravel AI Agent</h1>

<p align="center">
  <strong>🧠 Give your Laravel app a brain, safely.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/alidaaer/laravel-ai-agent"><img src="https://img.shields.io/packagist/v/alidaaer/laravel-ai-agent.svg?style=flat-square" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/alidaaer/laravel-ai-agent"><img src="https://img.shields.io/packagist/l/alidaaer/laravel-ai-agent.svg?style=flat-square" alt="License"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg?style=flat-square" alt="PHP Version"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/laravel-%3E%3D10.0-FF2D20.svg?style=flat-square" alt="Laravel Version"></a>
</p>

<p align="center">
  Build AI Agents that execute <strong>real actions</strong> in your Laravel application with minimal code.
</p>

---

Laravel AI Agent is the ultimate **Laravel AI package** for building intelligent automation. Transform your Laravel app with **AI-powered agents** that can understand natural language and execute PHP methods directly. Perfect for **Laravel GPT integration**, **AI automation**, and **function calling**. This Laravel AI solution works with OpenAI, Anthropic Claude, Gemini, and more - making it the most flexible **AI assistant for Laravel**.

## ✨ Why Laravel AI Agent?

| Feature | Description |
|---------|-------------|
| 🚀 **Zero Boilerplate** | Turn any method into an AI tool with a single attribute |
| 🧠 **Smart Auto-Inference** | Auto-generates descriptions and infers types from parameter names |
| 💬 **Chat Widget** | Beautiful, customizable Web Component - just drop it in! |
| 🔌 **Multi-Provider** | OpenAI, Anthropic Claude, Google Gemini, DeepSeek, OpenRouter |
| 💾 **Memory** | AI-powered summarization with smart pointer tracking — session or database |
| 📊 **Markdown Responses** | Tables, formatting, and rich text in chat |
| ⚡ **Smart Returns** | `view()`, `redirect()`, `Model` — AI understands them all |
| 🤖 **Multi-Agent** | Class-based agents with per-method access control |
| 🛡️ **Security Built-in** | Prompt injection detection, XSS prevention, secret redaction |
| 🎯 **Laravel Native** | Feels like part of the framework |

---

## 📦 Installation

```bash
composer require alidaaer/laravel-ai-agent
php artisan vendor:publish --tag=ai-agent-config
```

Add to your `.env`:

```env
# AI Driver (openai, anthropic, gemini, deepseek, openrouter)
AI_AGENT_DRIVER=openai
AI_AGENT_API_KEY=sk-...
AI_AGENT_MODEL=gpt-4o-mini

```

Run migrations for conversation history:

```bash
php artisan migrate
```

---

## 🚀 Quick Start

### 1. Add the Chat Widget ⚡

Drop it into any Blade view — **routes are auto-registered!**

```blade
@aiAgentWidget
<script src="/ai-agent/widget.js"></script>
```

**Open the page, click the bubble, start talking.** You already have a working AI chatbot! 🎉

All widget settings (theme, language, position, etc.) are read from `config/ai-agent.php`:

```php
'widget' => [
    'theme' => 'dark',
    'rtl' => false,
    'primary_color' => '#6366f1',
    'position' => 'bottom-right',
    'system_prompt' => 'You are a helpful shop assistant.',
],
```

> 💡 The `system_prompt` is set in config (not HTML) so it stays hidden from the client.

### 2. Give AI Your Tools (Zero-Config!)

```php
use LaravelAIAgent\Attributes\AsAITool;

class ProductService
{
    #[AsAITool]  // Description auto-generated: "List products" ✨
    public function listProducts(): array
    {
        return Product::all()->toArray();
    }

    #[AsAITool]  // Types inferred: $price→number, $stock→integer
    public function addProduct(string $name, float $price, int $stock = 0): array
    {
        return Product::create(compact('name', 'price', 'stock'))->toArray();
    }
}
```

Place it **anywhere in `app/`** — the package auto-discovers all `#[AsAITool]` methods. Now say *"Add a product called iPhone for $999"* and it actually does it! 🚀

> 🤖 **Need custom agents?** Create dedicated agents with `php artisan make:agent` — see [Multi-Agent System](#-multi-agent-system).

---

## 💬 Chat Widget Component

A beautiful, drop-in Web Component for AI chat — with conversations, i18n, and stop button built-in.

### Full-Featured Example

```blade
{{-- Reads all settings from config/ai-agent.php --}}
@aiAgentWidget
<script src="/ai-agent/widget.js"></script>
```

Or use the PHP helper directly with overrides:

```blade
{!! \LaravelAIAgent\Widget::render(['theme' => 'light', 'lang' => 'ar']) !!}
<script src="/ai-agent/widget.js"></script>
```

### All Options

| Attribute | Description | Default |
|-----------|-------------|----------|
| `endpoint` | Chat API URL | Required |
| `stream` | Enable SSE streaming (boolean) | — |
| `history-endpoint` | Load conversation history + conversations sidebar | — |
| `persist-messages` | Keep messages across page reloads (boolean) | — |
| `theme` | `light` or `dark` | `dark` |
| `lang` | Language: `en`, `ar`, `fr`, `es`, `zh` | `en` |
| `rtl` | Right-to-left mode (boolean) | Auto for `ar` |
| `title` | Header title | `AI Assistant` |
| `subtitle` | Header subtitle | — |
| `welcome-message` | First bot message | — |
| `placeholder` | Input placeholder | `Type your message...` |
| `primary-color` | Theme color | `#6366f1` |
| `position` | `bottom-right`, `bottom-left`, `top-right`, `top-left` | `bottom-right` |
| `width` | Widget width | `400px` |
| `height` | Widget height | `600px` |
| `button-icon` | Floating button icon (URL or emoji) | `💬` |
| `button-size` | Floating button size | `56px` |

### Features

- ✅ **SSE Streaming** — Real-time streaming with tool execution progress
- ✅ **Markdown Support** — Tables, bold, code blocks with copy button, lists
- ✅ **Voice Input** — Built-in Web Speech API microphone button
- ✅ **i18n** — 5 languages built-in (EN, AR, FR, ES, ZH)
- ✅ **RTL Support** — Auto-detected for Arabic, Hebrew, Farsi
- ✅ **Stop Button** — Cancel AI responses mid-generation
- ✅ **Conversations Sidebar** — Switch between past conversations (isolated per agent)
- ✅ **Keyboard Shortcuts** — Escape to close, Enter to send, Shift+Enter for new line
- ✅ **Mobile Responsive** — Full-screen on mobile
- ✅ **No Dependencies** — Pure Web Component

---

## 🔧 Chat API

Routes are **auto-registered** — no setup needed! The widget works out of the box with:

```
POST /ai-agent/chat           → General chat
GET  /ai-agent/history        → Conversation history
GET  /ai-agent/conversations  → List conversations
```

Need a custom endpoint? Easy:

```php
// routes/api.php
Route::post('/my-chat', function () {
    $response = Agent::conversation(request('conversation_id'))
        ->system('You are a helpful shop assistant')
        ->tools([ProductService::class])
        ->chat(request('message'));

    return response()->json(['response' => $response]);
});
```

---

## 🛠️ Creating Tools

### Zero-Config (Recommended)

```php
#[AsAITool]  // Description: "List Products" (from method name)
public function listProducts(): array { }

#[AsAITool]  // Description: "Add Product"
public function addProduct(string $name, float $price): array { }
```

### With Custom Description

```php
#[AsAITool('Search for products by name or category')]
public function search(string $query): array { }
```

### With Custom Parameters

The package auto-discovers parameters from type hints. But if your method uses `Request` or you want more control, define them manually with `name:type` syntax:

```php
#[AsAITool(
    description: 'Update product details',
    params: [
        'id:integer' => 'Product ID to update',
        'name' => 'New product name',          // type auto-inferred as string
        'price:number' => 'New price in USD',
    ]
)]
public function updateProduct(Request $request): array
{
    $product = Product::findOrFail($request->input('id'));
    $product->update($request->only(['name', 'price']));
    return ['success' => true, 'product' => $product->toArray()];
}
```

Supported types: `string`, `integer`, `number`, `boolean`, `array`. Without `:type`, the type is inferred from the parameter name (`id` → integer, `price` → number).

> 💡 **When to use `params`?** Only when auto-discovery isn't enough — e.g., dynamic `Request` inputs, or when you want custom descriptions for the AI.


### Smart Type Inference

No type hints? We infer from names:

| Parameter Name | Inferred Type |
|----------------|---------------|
| `$id`, `$productId`, `$userId` | `integer` |
| `$price`, `$total`, `$amount` | `number` |
| `$isActive`, `$hasItems`, `$enabled` | `boolean` |
| `$items`, `$products`, `$users` | `array` |
| Other | `string` |

---

## ⚡ Smart Return Handling

**Use your existing methods as AI tools — no refactoring needed.**

The agent automatically understands any return type: `view()`, `redirect()`, `Model`, `Collection`, `JsonResponse`, and even catches exceptions and validation errors gracefully.

### Zero-Config — It Just Works

```php
#[AsAITool]
public function showProduct(int $id)
{
    return view('product.show', ['product' => Product::findOrFail($id)]);
    // AI receives: {"product": {"id": 1, "name": "iPhone", "price": 999}} ✨
}

#[AsAITool]
public function activateProduct(int $id)
{
    Product::findOrFail($id)->update(['is_active' => true]);
    return redirect()->back()->with('message', 'Product activated!');
    // AI receives: {"message": "Product activated!"} ✨
}
```

### Exceptions & Validation — Handled Automatically

```php
#[AsAITool]
public function createProduct(string $name, float $price)
{
    $validator = Validator::make(compact('name', 'price'), [
        'name' => 'required|min:3',
        'price' => 'required|numeric|min:0.01',
    ]);

    if ($validator->fails()) throw new ValidationException($validator);
    // AI tells the user: "Product name must be at least 3 characters" 🛡️

    return Product::create(compact('name', 'price'))->toArray();
}
```

### `isAICall()` — Full Control When You Need It

Customize responses for AI vs Web with a single helper:

```php
#[AsAITool]
public function listProducts()
{
    $products = Product::all();

    if (isAICall()) {
        return ['count' => $products->count(), 'products' => $products->toArray()];
    }

    return view('products.index', compact('products'));
}
```

> **One method, two audiences.** Web users get a Blade view, AI gets structured data.

| Return Type | What AI Receives |
|---|---|
| `view('...', $data)` | The `$data` variables directly |
| `redirect()->with('message', '...')` | `{"message": "..."}` |
| `Eloquent Model` | `model->toArray()` |
| `Collection` | `collection->toArray()` |
| `JsonResponse` | The JSON data |
| `ValidationException` | Error messages in user's language |
| `Any Exception` | Error message for AI to report |

---

## 🔌 Providers

```php
// OpenAI (default)
Agent::driver('openai')->chat("Hello");

// Google Gemini
Agent::driver('gemini')->chat("Hello");

// Anthropic Claude
Agent::driver('anthropic')->chat("Hello");

// OpenRouter (100+ models via single API)
Agent::driver('openrouter')->model('anthropic/claude-3.5-sonnet')->chat("Hello");

// Specific model override
Agent::driver('openai')->model('gpt-4o')->chat("Hello");
```

---

## 🤖 Multi-Agent System

Create dedicated agent classes with isolated tools, permissions, and conversations.

### 1. Create an Agent

```bash
php artisan make:agent ShopAgent
php artisan make:agent AdminAgent
```

This generates a class in `app/AI/Agents/` and **auto-registers** it in `config/ai-agent.php`.

```php
// app/AI/Agents/ShopAgent.php
class ShopAgent extends BaseAgent
{
    public function instructions(): string
    {
        return 'You are a friendly shop assistant. Help customers browse and order.';
    }

    public function tools(): array
    {
        return [\App\Services\ShopService::class];
    }

    // Optional: customize driver, model, middleware, widget...
    // public function driver(): ?string { return 'openai'; }
    // public function model(): ?string { return 'gpt-4o-mini'; }
    // public function routeMiddleware(): array { return ['web', 'auth']; }
}
```

### 2. Scope Tools Per Agent

Use class references for IDE autocompletion and refactor safety:

```php
use App\AI\Agents\AdminAgent;

class OrderService
{
    #[AsAITool]                                        // 👈 All agents see this
    public function listOrders(): array { /* ... */ }

    #[AsAITool(agents: [AdminAgent::class])]           // 👈 Admin only
    public function deleteOrder(int $id) { /* ... */ }

    #[AsAITool(agents: [AdminAgent::class])]           // 👈 Admin only
    public function advancedStats() { /* ... */ }
}
```

**Rule:** No `agents` param = available to **all** agents. Explicit list = restricted.

### 3. Auto-Generated Endpoints

Each agent gets its own isolated routes — **automatically**:

```
POST   /ai-agent/shop/chat           → Chat with ShopAgent
POST   /ai-agent/shop/chat-stream    → SSE streaming chat
GET    /ai-agent/shop/conversations  → List ShopAgent conversations only
GET    /ai-agent/shop/history        → Conversation history
DELETE /ai-agent/shop/history        → Clear conversation

POST   /ai-agent/admin/chat          → Chat with AdminAgent (sees deleteOrder, advancedStats)
GET    /ai-agent/admin/conversations → List AdminAgent conversations only
```

**Conversations are isolated per agent** — each agent sees only its own conversation history.

### 4. Add Widget to Blade

Each agent renders its own fully-configured widget:

```php
// In any Blade view
{!! \App\AI\Agents\ShopAgent::widget() !!}
{!! \App\AI\Agents\AdminAgent::widget() !!}
<script src="/ai-agent/widget.js"></script>
```

Each widget automatically uses the correct endpoints, theme, language, and position defined in the agent's `widgetConfig()`.

### 5. Customize Widget Appearance

```php
class AdminAgent extends BaseAgent
{
    public function widgetConfig(): array
    {
        return [
            'title' => 'Admin AI',
            'theme' => 'light',
            'lang' => 'ar',
            'primary_color' => '#ef4444',
            'position' => 'bottom-left',
        ];
    }
}
```

### 6. Mobile / API Usage

```dart
// Flutter example
final response = await http.post(
  Uri.parse('https://yourapp.com/ai-agent/shop/chat'),
  body: {'message': 'Show my orders', 'conversation_id': conversationId},
);
```

> **No agents?** Everything works without agents — use the generic `/ai-agent/chat` endpoint with the widget directly.

---

## 💾 Conversation Memory

```php
// Conversations are remembered!
Agent::conversation('user-123')
    ->tools([OrderService::class])
    ->chat("Show my orders");

// Later...
Agent::conversation('user-123')
    ->chat("Cancel the last one");
// AI remembers the context!
```

**Smart Memory Management:**
- After every `summarize_after` messages, the AI generates a concise summary of older messages
- Messages are **never deleted** until reaching `max_messages` hard limit
- The LLM receives: `[summary of old context]` + `[last N recent messages]` + `[new message]`
- Falls back to manual summarization if AI summarization fails
- Disable AI summarization with `AI_AGENT_AI_SUMMARY=false` in `.env`

---

## ⚙️ Configuration

```php
// config/ai-agent.php
return [
    'default' => env('AI_AGENT_DRIVER', 'openai'),
    'verify_ssl' => env('AI_AGENT_VERIFY_SSL', false),
    
    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL',env('AI_AGENT_MODEL','gpt-4o-mini')),
        ],
        'anthropic' => [ /* ... */ ],
        'gemini'    => [ /* ... */ ],
        'deepseek'  => [ /* ... */ ],
        'openrouter' => [ /* ... */ ],
    ],
    
    'discovery' => [
        'paths' => [app_path()],    // Scans all app/ by default
        'cache' => true,            // Cache discovered tools
    ],

    'memory' => [
        'driver' => env('AI_AGENT_MEMORY', 'session'),
        'summarize_after' => 10,    // AI-summarize every N messages
        'max_messages' => 100,      // Hard limit — delete oldest beyond this
        'recent_messages' => 4,     // Send last N messages to LLM
        'ai_summarization' => true, // Use AI for smart summaries
    ],

    'agents' => [
        \App\AI\Agents\ShopAgent::class,
        \App\AI\Agents\AdminAgent::class,
    ],

    'security' => [
        'enabled' => true,          // All security on by default
        'max_tool_calls_per_request' => 10,
        'max_iterations' => 10,
    ],
];
```

> 📖 See [Full Documentation](documentation.md) for all configuration options.

---

## 📡 Events

```php
use LaravelAIAgent\Events\ToolCalled;
use LaravelAIAgent\Events\ToolExecuted;

Event::listen(ToolCalled::class, function ($event) {
    Log::info("AI called: " . $event->tool['name']);
});

Event::listen(ToolExecuted::class, function ($event) {
    Log::info("Result: " . json_encode($event->result));
});
```

---

## 📖 Full Example

```php
// 1️⃣ Service with tools — place anywhere in app/
class ShopService
{
    #[AsAITool]
    public function listProducts(): array {
        return Product::all()->toArray();
    }

    #[AsAITool]
    public function addProduct(string $name, float $price): array {
        return Product::create(compact('name', 'price'))->toArray();
    }
}
```

```bash
# 2️⃣ Create an agent
php artisan make:agent ShopAgent
```

```php
// 3️⃣ Drop the widget in Blade — routes are auto-registered!
{!! \App\AI\Agents\ShopAgent::widget() !!}
<script src="/ai-agent/widget.js"></script>
```

**That's it.** Tools are auto-discovered, routes are auto-registered, conversations are isolated per agent, memory is auto-managed. 🎉

---

## 📖 Documentation

For the full detailed documentation — including all configuration options, security features, event system, streaming, and more — see **[documentation.md](documentation.md)**.

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

The MIT License (MIT). See [License File](LICENSE.md) for more information.

---

<p align="center">
  Made with ❤️ for Laravel developers
</p>
