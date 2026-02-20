# 🧪 دليل تجربة Laravel AI Agent

## الطريقة 1: تجربة في مشروع Laravel موجود

### 1. ربط الباكج محلياً

أضف هذا في `composer.json` لمشروعك:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-ai-agent"
        }
    ],
    "require": {
        "alidaaer/laravel-ai-agent": "*"
    }
}
```

ثم نفذ:
```bash
composer update
```

### 2. إضافة API Key

في ملف `.env`:
```env
# لـ Gemini
GEMINI_API_KEY=your-api-key
AI_AGENT_DRIVER=gemini

# أو لـ OpenAI
OPENAI_API_KEY=sk-xxxxx
AI_AGENT_DRIVER=openai
```

### 3. إنشاء أداة للتجربة

```bash
php artisan make:service WeatherService
```

```php
// app/Services/WeatherService.php

namespace App\Services;

use LaravelAIAgent\Attributes\AsAITool;

class WeatherService
{
    #[AsAITool("Get current weather for a city")]
    public function getWeather(string $city): string
    {
        // Simulate weather data
        $temps = ['Riyadh' => 35, 'Jeddah' => 38, 'Dubai' => 40];
        $temp = $temps[$city] ?? rand(20, 40);
        
        return "Weather in {$city}: {$temp}°C ☀️";
    }

    #[AsAITool("Get weather forecast for next days")]
    public function getForecast(string $city, int $days = 3): array
    {
        $forecast = [];
        for ($i = 1; $i <= $days; $i++) {
            $forecast[] = [
                'day' => $i,
                'temp' => rand(25, 40),
                'condition' => ['sunny', 'cloudy', 'windy'][rand(0, 2)],
            ];
        }
        return $forecast;
    }
}
```

### 4. تجربة في Tinker

```bash
php artisan tinker
```

```php
use LaravelAIAgent\Facades\Agent;
use App\Services\WeatherService;

// تجربة بسيطة
Agent::chat("مرحبا!");

// تجربة مع Gemini
Agent::driver('gemini')->chat("ما هي عاصمة السعودية؟");

// تجربة مع Tools
Agent::tools([WeatherService::class])
    ->chat("كيف الطقس في الرياض؟");
```

### 5. تجربة في Terminal

```bash
php artisan agent:chat --driver=gemini
```

---

## الطريقة 2: مشروع تجريبي سريع

### 1. إنشاء مشروع جديد

```bash
composer create-project laravel/laravel test-agent
cd test-agent
```

### 2. ربط الباكج

```bash
composer config repositories.ai-agent path ../laravel-ai-agent
composer require alidaaer/laravel-ai-agent:*
```

### 3. إعداد API Key

```bash
# إضافة للـ .env
echo "GEMINI_API_KEY=your-key" >> .env
echo "AI_AGENT_DRIVER=gemini" >> .env
```

### 4. إنشاء Route للتجربة

```php
// routes/web.php

use Illuminate\Support\Facades\Route;
use LaravelAIAgent\Facades\Agent;

Route::get('/chat', function () {
    $response = Agent::driver('gemini')
        ->system('أنت مساعد ذكي يتحدث العربية')
        ->chat('مرحبا! عرفني بنفسك');
    
    return response()->json([
        'response' => $response
    ]);
});
```

```bash
php artisan serve
# افتح http://localhost:8000/chat
```

---

## الطريقة 3: تشغيل الاختبارات

```bash
cd /path/to/laravel-ai-agent

# تثبيت dependencies
composer install

# تشغيل الاختبارات
./vendor/bin/phpunit

# أو اختبار محدد
./vendor/bin/phpunit --filter=ToolDiscoveryTest
```

---

## أمثلة سريعة

### Chat بسيط
```php
$response = Agent::chat("ما هي PHP؟");
```

### مع System Prompt
```php
$response = Agent::system("أنت مبرمج محترف")
    ->chat("كيف أكتب function في PHP؟");
```

### مع Gemini
```php
$response = Agent::driver('gemini')
    ->model('gemini-2.5-flash-preview-05-20')
    ->chat("اشرح لي machine learning");
```

### مع Model Context
```php
$user = User::find(1);
$orders = $user->orders;

$response = Agent::for($user, $orders)
    ->chat("كم عدد طلبات هذا العميل؟");
```

### Streaming
```php
Agent::driver('gemini')->stream("اكتب قصة قصيرة", function($chunk) {
    echo $chunk;
});
```

---

## Troubleshooting

### خطأ: Class not found
```bash
composer dump-autoload
```

### خطأ: API Key
تأكد من:
- `GEMINI_API_KEY` في `.env`
- `AI_AGENT_DRIVER=gemini` في `.env`
- تشغيل `php artisan config:clear`

### خطأ: Memory
```bash
php artisan cache:clear
php artisan config:clear
```
