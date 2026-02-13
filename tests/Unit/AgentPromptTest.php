<?php

namespace LaravelAIAgent\Tests\Unit;

use LaravelAIAgent\AgentPrompt;
use PHPUnit\Framework\TestCase;

class AgentPromptTest extends TestCase
{
    public function test_creates_prompt_with_defaults(): void
    {
        $prompt = new AgentPrompt('Hello');

        $this->assertEquals('Hello', $prompt->message);
        $this->assertNull($prompt->system);
        $this->assertEmpty($prompt->history);
        $this->assertEmpty($prompt->options);
        $this->assertEmpty($prompt->tools);
        $this->assertNull($prompt->conversationId);
    }

    public function test_creates_prompt_with_all_fields(): void
    {
        $prompt = new AgentPrompt(
            message: 'Hello',
            system: 'You are a helper',
            history: [['role' => 'user', 'content' => 'Hi']],
            options: ['temperature' => 0.5],
            tools: ['tool1'],
            conversationId: 'conv-123',
        );

        $this->assertEquals('Hello', $prompt->message);
        $this->assertEquals('You are a helper', $prompt->system);
        $this->assertCount(1, $prompt->history);
        $this->assertEquals(0.5, $prompt->options['temperature']);
        $this->assertEquals(['tool1'], $prompt->tools);
        $this->assertEquals('conv-123', $prompt->conversationId);
    }

    public function test_prompt_properties_are_mutable(): void
    {
        $prompt = new AgentPrompt('Original');

        $prompt->message = 'Modified';
        $prompt->system = 'New system prompt';

        $this->assertEquals('Modified', $prompt->message);
        $this->assertEquals('New system prompt', $prompt->system);
    }

    public function test_to_array(): void
    {
        $prompt = new AgentPrompt(
            message: 'Hello',
            system: 'Be helpful',
        );

        $arr = $prompt->toArray();

        $this->assertEquals('Hello', $arr['message']);
        $this->assertEquals('Be helpful', $arr['system']);
        $this->assertArrayHasKey('history', $arr);
        $this->assertArrayHasKey('options', $arr);
    }
}
