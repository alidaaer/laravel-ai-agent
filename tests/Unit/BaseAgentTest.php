<?php

namespace LaravelAIAgent\Tests\Unit;

use LaravelAIAgent\BaseAgent;
use PHPUnit\Framework\TestCase;

class BaseAgentTest extends TestCase
{
    public function test_instructions_are_required(): void
    {
        $agent = new ConcreteTestAgent();
        $this->assertEquals('You are a test agent.', $agent->instructions());
    }

    public function test_default_methods_return_null(): void
    {
        $agent = new ConcreteTestAgent();

        $this->assertNull($agent->driver());
        $this->assertNull($agent->model());
        $this->assertNull($agent->temperature());
        $this->assertNull($agent->maxTokens());
        $this->assertNull($agent->schema());
    }

    public function test_default_tools_returns_empty_array(): void
    {
        $agent = new ConcreteTestAgent();
        $this->assertEmpty($agent->tools());
    }

    public function test_default_middleware_returns_empty_array(): void
    {
        $agent = new ConcreteTestAgent();
        $this->assertEmpty($agent->middleware());
    }

    public function test_widget_config_returns_only_non_null(): void
    {
        $agent = new ConcreteTestAgent();
        $config = $agent->widgetConfig();

        $this->assertArrayHasKey('title', $config);
        $this->assertArrayHasKey('lang', $config);
        $this->assertEquals('Test Widget', $config['title']);
        $this->assertEquals('en', $config['lang']);

        $this->assertArrayNotHasKey('subtitle', $config);
        $this->assertArrayNotHasKey('theme', $config);
        $this->assertArrayNotHasKey('primary_color', $config);
    }

    public function test_widget_config_empty_when_no_overrides(): void
    {
        $agent = new MinimalTestAgent();
        $config = $agent->widgetConfig();

        $this->assertEmpty($config);
    }

    public function test_with_conversation_returns_self(): void
    {
        $agent = new ConcreteTestAgent();
        $result = $agent->withConversation('test-123');

        $this->assertSame($agent, $result);
    }

    public function test_with_context_returns_self(): void
    {
        $agent = new ConcreteTestAgent();
        $model = new \stdClass();
        $result = $agent->withContext($model);

        $this->assertSame($agent, $result);
    }
}

// =========================================================================
// Test Fixtures
// =========================================================================

class ConcreteTestAgent extends BaseAgent
{
    public function instructions(): string
    {
        return 'You are a test agent.';
    }

    public function widgetTitle(): ?string
    {
        return 'Test Widget';
    }

    public function widgetLang(): ?string
    {
        return 'en';
    }
}

class MinimalTestAgent extends BaseAgent
{
    public function instructions(): string
    {
        return 'Minimal.';
    }
}

class ArabicTestAgent extends BaseAgent
{
    public function instructions(): string
    {
        return 'أنت وكيل اختبار.';
    }

    public function widgetLang(): ?string
    {
        return 'ar';
    }
}
