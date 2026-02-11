<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

<h1 align="center">Laravel AI Agent</h1>

<p align="center">
  <strong>🧠 Give your Laravel app a brain, safely.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/alidaaer/laravel-ai-agent"><img src="https://img.shields.io/packagist/v/alidaaer/laravel-ai-agent.svg?style=flat-square" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/alidaaer/laravel-ai-agent"><img src="https://img.shields.io/packagist/dt/alidaaer/laravel-ai-agent.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/alidaaer/laravel-ai-agent"><img src="https://img.shields.io/packagist/l/alidaaer/laravel-ai-agent.svg?style=flat-square" alt="License"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg?style=flat-square" alt="PHP Version"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/laravel-%3E%3D10.0-FF2D20.svg?style=flat-square" alt="Laravel Version"></a>
</p>

<p align="center">
  Build AI Agents that execute <strong>real actions</strong> in your Laravel application with minimal code.
</p>

---

## ✨ Why Laravel AI Agent?

| Feature | Description |
|---------|-------------|
| 🚀 **Zero Boilerplate** | Turn any method into an AI tool with a single attribute |
| 🧠 **Smart Auto-Inference** | Auto-generates descriptions and infers types from parameter names |
| 💬 **Chat Widget** | Beautiful, customizable Web Component - just drop it in! |
|  **Multi-Provider** | OpenAI, Anthropic Claude, Google Gemini, DeepSeek, OpenRouter |
| 💾 **Memory** | AI-powered summarization with smart pointer tracking — session or database |
| 📊 **Markdown Responses** | Tables, formatting, and rich text in chat |
| ⚡ **Smart Returns** | `view()`, `redirect()`, `Model` — AI understands them all |
| 🤖 **Multi-Agent** | Multiple agents with per-method access control from config |
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
# AI Driver (openai, anthropic, gemini, openrouter)
AI_AGENT_DEFAULT=openai
OPENAI_API_KEY=sk-...

# Persistent memory (recommended)
AI_AGENT_MEMORY=database
```

Run migrations for conversation history:

```bash
php artisan migrate
```

---

## 🚀 Quick Start

### 1. Add the Chat Widget ⚡

Drop it into any Blade view — **routes are auto-registered!**

```html
<ai-agent-chat
    endpoint="/ai-agent/chat"
    theme="dark"
    title="AI Assistant"
></ai-agent-chat>

<script src="/ai-agent/widget.js"></script>
```

**Open the page, click the bubble, start talking.** You already have a working AI chatbot! 🎉

> 💡 **Customize the AI personality** — set `system_prompt` in `config/ai-agent.php`:
> ```php
> 'widget' => [
>     'system_prompt' => 'You are a helpful shop assistant for an electronics store.',
> ],
> ```
> This is set in config (not HTML) so it stays hidden from the client. See [Configuration](#️-configuration) for all widget options.

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

---

## 💬 Chat Widget Component

A beautiful, drop-in Web Component for AI chat — with conversations, i18n, and stop button built-in.

### Full-Featured Example

```html
<ai-agent-chat
    endpoint="/ai-agent/chat"
    history-endpoint="/ai-agent/history"
    conversations-endpoint="/ai-agent/conversations"
    theme="dark"
    title="Shop Assistant"
    welcome-message="Hello! How can I help you today?"
    lang="en"
    primary-color="#6366f1"
></ai-agent-chat>

<script src="/ai-agent/widget.js"></script>
```

### All Options

| Attribute | Description | Default |
|-----------|-------------|---------|
| `endpoint` | Chat API URL | Required |
| `theme` | `light` or `dark` | `dark` |
| `lang` | Language: `en`, `ar`, `fr`, `es`, `zh` | `en` |
| `rtl` | Right-to-left mode | Auto for `ar` |
| `title` | Header title | `AI Assistant` |
| `subtitle` | Header subtitle | — |
| `welcome-message` | First bot message | — |
| `placeholder` | Input placeholder | `Type your message...` |
| `primary-color` | Theme color | `#6366f1` |
| `position` | `bottom-right` or `bottom-left` | `bottom-right` |
| `history-endpoint` | Load conversation history | — |
| `conversations-endpoint` | Enable conversations sidebar | — |

### Features

- ✅ **Markdown Support** — Tables, bold, code, lists
- ✅ **i18n** — 5 languages built-in (EN, AR, FR, ES, ZH)
- ✅ **RTL Support** — Auto-detected for Arabic, Hebrew, Farsi
- ✅ **Stop Button** — Cancel AI responses mid-generation
- ✅ **Conversations Sidebar** — Switch between past conversations
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

### With Validation Rules

```php
use LaravelAIAgent\Attributes\Rules;

#[AsAITool('Send email to customer')]
public function sendEmail(
    #[Rules('required|email')] string $email,
    #[Rules('required|max:100')] string $subject,
    #[Rules('required')] string $body
): string {
    // Validation happens automatically!
    Mail::to($email)->send(new CustomerEmail($subject, $body));
    return "Email sent!";
}
```

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

Define multiple agents with isolated tools and permissions — **all from config**.

### 💡 Real-World Example: E-Commerce App

Imagine you're building a mobile app with **one Laravel backend** powering three different AI assistants:

| Agent | Who uses it | Can do |
|-------|------------|--------|
| 🛒 **shop** | Customers (mobile app) | Browse products, track orders, get help |
| 📊 **admin** | Store managers (dashboard) | All above + delete orders, view stats, manage inventory |
| 🎧 **support** | Support team (internal) | All above + refunds, access customer data, escalate tickets |

**One codebase. Three agents. Zero duplication.** 🔥

### 1. Define Agents

```php
// config/ai-agent.php
'agents' => [
    'shop' => [
        'system_prompt' => 'You are a friendly shop assistant for our web and mobile app customers.',
        'middleware' => ['api', 'auth:sanctum'],
    ],
    'admin' => [
        'system_prompt' => 'You are an admin assistant with full store management access.',
        'middleware' => ['api', 'auth:sanctum', 'role:admin'],
    ],
    'support' => [
        'system_prompt' => 'You are a support agent. Be empathetic and resolve issues quickly.',
        'middleware' => ['api', 'auth:sanctum', 'role:support'],
    ],
],
```

### 2. Scope Tools Per-Method

```php
class OrderService
{
    #[AsAITool]                                        // 👈 All agents see this
    public function listOrders(): array { /* ... */ }

    #[AsAITool(agents: ['admin', 'support'])]          // 👈 Admin + Support only
    public function deleteOrder(int $id) { /* ... */ }

    #[AsAITool(agents: ['admin'])]                     // 👈 Admin only
    public function advancedStats() { /* ... */ }

    #[AsAITool(agents: ['support'])]                   // 👈 Support only
    public function issueRefund(int $orderId) { /* ... */ }
}
```

**Rule:** No `agents` param = available to **all** agents. Explicit list = restricted.

### 3. Auto-Generated Endpoints

Each agent gets its own route — **automatically**:

```
POST /ai-agent/shop/chat      → sees: listOrders
POST /ai-agent/admin/chat     → sees: listOrders, deleteOrder, advancedStats
POST /ai-agent/support/chat   → sees: listOrders, deleteOrder, issueRefund
```

### 4. Connect — Web, Mobile, Anywhere

**Web store** — drop in the widget:
```html
<ai-agent-chat endpoint="/ai-agent/shop/chat" title="Shop Assistant"></ai-agent-chat>
```

**Admin dashboard:**
```html
<ai-agent-chat endpoint="/ai-agent/admin/chat" title="Admin AI" theme="light"></ai-agent-chat>
```

**Support panel:**
```html
<ai-agent-chat endpoint="/ai-agent/support/chat" title="Support AI"></ai-agent-chat>
```

**Mobile app** (Flutter, React Native, etc.) — just call the API:
```dart
// Flutter example
final response = await http.post(
  Uri.parse('https://yourapp.com/ai-agent/shop/chat'),
  body: {'message': 'Show my orders', 'conversation_id': conversationId},
);
```

**Boom!** 💥 One Laravel backend powering your website, admin dashboard, support panel, AND mobile app — each with its own AI personality and permissions.

### 5. Programmatic Usage

```php
Agent::agent('shop')->conversation($id)->chat('Show my orders');
Agent::agent('admin')->chat('Delete order 5');
```

> **Zero agents config?** Everything works like before — single agent, all tools discovered automatically.

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
    'default' => env('AI_AGENT_DEFAULT', 'openai'),
    'verify_ssl' => env('AI_AGENT_VERIFY_SSL', false),
    
    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
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

    'security' => [
        'enabled' => true,          // All security on by default
        'max_tool_calls_per_request' => 10,
        'max_iterations' => 5,
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

```html
<!-- 2️⃣ Drop the widget — routes are auto-registered! -->
<ai-agent-chat
    endpoint="/ai-agent/chat"
    history-endpoint="/ai-agent/history"
    conversations-endpoint="/ai-agent/conversations"
    theme="dark"
    title="Shop Assistant"
></ai-agent-chat>
<script src="/ai-agent/widget.js"></script>
```

**That's it.** Tools are auto-discovered, routes are auto-registered, memory is auto-managed. 🎉

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
