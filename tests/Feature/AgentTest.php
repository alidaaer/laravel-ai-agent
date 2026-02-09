<?php

namespace LaravelAIAgent\Tests\Feature;

use LaravelAIAgent\Tests\TestCase;
use LaravelAIAgent\Agent;
use LaravelAIAgent\Testing\FakeDriver;
use LaravelAIAgent\Attributes\AsAITool;

class AgentTest extends TestCase
{
    public function test_agent_can_chat(): void
    {
        $fakeDriver = new FakeDriver();
        $fakeDriver->addResponse('Hello! How can I help you?');

        $agent = new Agent('Test');
        $this->setDriverOnAgent($agent, $fakeDriver);

        $response = $agent->chat('Hello');

        $this->assertEquals('Hello! How can I help you?', $response);
    }

    public function test_agent_can_use_system_prompt(): void
    {
        $fakeDriver = new FakeDriver();
        $fakeDriver->addResponse('أهلاً وسهلاً!');

        $agent = new Agent('Test');
        $this->setDriverOnAgent($agent, $fakeDriver);

        $agent->system('You are a helpful assistant that speaks Arabic');
        $response = $agent->chat('Hello');

        $prompts = $fakeDriver->getPrompts();
        $this->assertStringContains('Arabic', $prompts[0]['options']['system']);
    }

    public function test_agent_calls_tool_when_needed(): void
    {
        $fakeDriver = new FakeDriver();
        
        // First response: tool call
        $fakeDriver->addResponse([
            'content' => '',
            'tool_calls' => [
                ['id' => '1', 'name' => 'getWeather', 'arguments' => ['city' => 'Riyadh']],
            ],
        ]);
        
        // Second response: final answer
        $fakeDriver->addResponse([
            'content' => 'The weather in Riyadh is sunny.',
            'tool_calls' => [],
        ]);

        $agent = new Agent('Test');
        $this->setDriverOnAgent($agent, $fakeDriver);
        $agent->tools([WeatherService::class]);

        $response = $agent->chat('What is the weather in Riyadh?');

        $this->assertEquals('The weather in Riyadh is sunny.', $response);
        $fakeDriver->assertToolCalled('getWeather', ['city' => 'Riyadh']);
    }

    public function test_agent_binds_model_context(): void
    {
        $fakeDriver = new FakeDriver();
        $fakeDriver->addResponse('The order status is pending.');

        $agent = new Agent('Test');
        $this->setDriverOnAgent($agent, $fakeDriver);

        // Create a mock model
        $order = new class {
            public function toArray() {
                return ['id' => 123, 'status' => 'pending'];
            }
        };

        $agent->for($order)->chat('What is the status?');

        $prompts = $fakeDriver->getPrompts();
        $this->assertStringContains('123', $prompts[0]['options']['system']);
        $this->assertStringContains('pending', $prompts[0]['options']['system']);
    }

    protected function setDriverOnAgent(Agent $agent, FakeDriver $driver): void
    {
        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $property->setValue($agent, $driver);
    }

    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}

// Test fixture
class WeatherService
{
    #[AsAITool('Get weather for a city')]
    public function getWeather(string $city): string
    {
        return "Sunny, 35°C";
    }
}
