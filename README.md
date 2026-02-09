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
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg?style=flat-square" alt="PHP Version"></a>
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
| 🛡️ **Security First** | Laravel Validation rules for AI decisions |
| 🔌 **Multi-Provider** | OpenAI, Anthropic Claude, Google Gemini, Ollama |
| 💾 **Memory** | Conversations are automatically remembered |
| 📊 **Markdown Responses** | Tables, formatting, and rich text in chat |
| ⚡ **Smart Returns** | `view()`, `redirect()`, `Model` — AI understands them all |
| 🤖 **Multi-Agent** | Multiple agents with per-method access control from config |
| 🎯 **Laravel Native** | Feels like part of the framework |

---

## 📦 Installation

```bash
composer require alidaaer/laravel-ai-agent
```

Add your API key to `.env`:

```env
# Choose your provider
GEMINI_API_KEY=your-key-here
# or OPENAI_API_KEY=sk-xxxxx
# or ANTHROPIC_API_KEY=sk-ant-xxxxx
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=ai-agent-config
```

---

## 🚀 Quick Start

### 1. Create a Tool (Zero-Config!)

```php
use LaravelAIAgent\Attributes\AsAITool;

class ProductService
{
    #[AsAITool]  // That's it! Description auto-generated ✨
    public function listProducts(): array
    {
        return Product::all()->toArray();
    }

    #[AsAITool]  // Types inferred from names: $price→number, $stock→integer
    public function addProduct(string $name, float $price, int $stock = 0): array
    {
        return Product::create(compact('name', 'price', 'stock'))->toArray();
    }
}
```

### 2. Chat with AI

```php
use LaravelAIAgent\Facades\Agent;

$response = Agent::driver('gemini')
    ->system('You are a shop assistant')
    ->tools([ProductService::class])
    ->chat('Show me all products');

// AI calls listProducts() and returns formatted response!
```

### 3. Add Chat Widget (Optional)

```html
<ai-agent-chat
    endpoint="/api/chat"
    theme="dark"
    rtl
    title="AI Assistant"
></ai-agent-chat>

<script src="/ai-agent/widget.js"></script>
```

**That's it!** You now have a fully functional AI assistant. 🎉

---

## 💬 Chat Widget Component

A beautiful, drop-in Web Component for AI chat.

### Basic Usage

```html
<ai-agent-chat
    endpoint="/api/chat"
    theme="dark"
    title="AI Assistant"
    welcome-message="Hello! How can I help?"
></ai-agent-chat>

<script src="/ai-agent/widget.js"></script>
```

### All Options

| Attribute | Description | Default |
|-----------|-------------|---------|
| `endpoint` | Your chat API URL | `/api/chat` |
| `theme` | `light` or `dark` | `dark` |
| `rtl` | Right-to-left (Arabic, Hebrew) | `false` |
| `title` | Header title | `AI Assistant` |
| `subtitle` | Header subtitle | - |
| `welcome-message` | First bot message | - |
| `placeholder` | Input placeholder | `Type your message...` |
| `primary-color` | Theme color | `#6366f1` |
| `position` | `bottom-right`, `bottom-left`, `top-right`, `top-left` | `bottom-right` |
| `button-icon` | Toggle button icon | `💬` |
| `width` / `height` | Window dimensions | `420px` / `550px` |
| `persist-messages` | Save messages in localStorage | `false` |

### Features

- ✅ **Markdown Support** - Tables, bold, code, lists
- ✅ **RTL Support** - Perfect for Arabic
- ✅ **Mobile Responsive** - Full-screen on mobile
- ✅ **Customizable** - Colors, icons, positions
- ✅ **No Dependencies** - Pure Web Component

---

## 🔧 Creating Chat API

Create an API endpoint for the widget:

```php
// routes/api.php
use LaravelAIAgent\Facades\Agent;
use App\Services\ProductService;

Route::post('/chat', function () {
    $message = request('message');
    $conversationId = request('conversation_id');

    $response = Agent::driver('gemini')
        ->conversation($conversationId)  // Enable memory
        ->system('You are a helpful shop assistant')
        ->tools([ProductService::class])
        ->chat($message);

    return response()->json([
        'response' => $response
    ]);
});
```

The widget expects a JSON response with a `response` field.

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
// Google Gemini
Agent::driver('gemini')->chat("Hello");

// OpenAI
Agent::driver('openai')->chat("Hello");

// Anthropic Claude
Agent::driver('anthropic')->chat("Hello");

// Ollama (Local)
Agent::driver('ollama')->chat("Hello");

// Specific model
Agent::driver('gemini')->model('gemini-2.5-pro')->chat("Hello");
```

---

## 🤖 Multi-Agent System

Define multiple agents with isolated tools and permissions — **all from config**.

### 1. Define Agents

```php
// config/ai-agent.php
'agents' => [
    'shop' => [
        'system_prompt' => 'You are a helpful shop assistant',
        'middleware' => ['api', 'auth'],
    ],
    'admin' => [
        'system_prompt' => 'You are an admin assistant with full access',
        'middleware' => ['api', 'auth', 'admin'],
    ],
],
```

### 2. Scope Tools Per-Method

```php
class OrderService
{
    #[AsAITool('List orders')]              // All agents see this
    public function listOrders() {}

    #[AsAITool('Delete order', agents: ['admin'])]   // Admin only
    public function deleteOrder(int $id) {}

    #[AsAITool('Advanced stats', agents: ['admin'])]  // Admin only
    public function advancedStats() {}
}
```

**Rule:** No `agents` param = available to all agents. Explicit list = restricted.

### 3. Auto-Generated Endpoints

Each agent gets its own route automatically:

```
POST /ai-agent/shop/chat   → sees: listOrders
POST /ai-agent/admin/chat  → sees: listOrders, deleteOrder, advancedStats
```

### 4. Connect Widget

```html
<ai-agent-chat endpoint="/ai-agent/shop/chat"></ai-agent-chat>   <!-- Customer -->
<ai-agent-chat endpoint="/ai-agent/admin/chat"></ai-agent-chat>   <!-- Admin -->
```

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

---

## ⚙️ Configuration

```php
// config/ai-agent.php
return [
    'default' => 'gemini',
    
    'verify_ssl' => env('AI_AGENT_VERIFY_SSL', false),
    
    'drivers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => 'gemini-2.5-flash',
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => 'gpt-4o-mini',
        ],
        // ...
    ],
    
    'discovery' => [
        'paths' => [
            app_path('Services'),
        ],
    ],
    
    'widget' => [
        'enabled' => true,
        'theme' => 'dark',
    ],
];
```

### Environment Variables

```env
AI_AGENT_DRIVER=gemini
GEMINI_API_KEY=your-key
AI_AGENT_VERIFY_SSL=false
```

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

## 🧪 Terminal Testing

```bash
php artisan agent:chat
php artisan agent:chat --driver=gemini
```

---

## 📖 Full Example

```php
// 1️⃣ Service with tools
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

// 2️⃣ API Route
Route::post('/api/chat', function () {
    return response()->json([
        'response' => Agent::driver('gemini')
            ->tools([ShopService::class])
            ->chat(request('message'))
    ]);
});

// 3️⃣ Blade View
<ai-agent-chat endpoint="/api/chat" rtl></ai-agent-chat>
<script src="/ai-agent/widget.js"></script>
```

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

The MIT License (MIT). See [License File](LICENSE.md) for more information.

---

<p align="center">
  Made with ❤️ for Laravel developers
</p>
