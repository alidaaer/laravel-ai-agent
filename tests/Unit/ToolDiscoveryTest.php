<?php

namespace LaravelAIAgent\Tests\Unit;

use LaravelAIAgent\Tests\TestCase;
use LaravelAIAgent\Tools\ToolDiscovery;
use LaravelAIAgent\Attributes\AsAITool;
use LaravelAIAgent\Attributes\Rules;

class ToolDiscoveryTest extends TestCase
{
    public function test_discovers_tools_from_class(): void
    {
        $discovery = new ToolDiscovery();
        
        $tools = $discovery->discover([TestToolService::class]);

        $this->assertCount(2, $tools);
        $this->assertEquals('findOrder', $tools[0]['name']);
        $this->assertEquals('cancelOrder', $tools[1]['name']);
    }

    public function test_extracts_description(): void
    {
        $discovery = new ToolDiscovery();
        $tools = $discovery->discover([TestToolService::class]);

        $this->assertEquals('Find an order by ID', $tools[0]['description']);
    }

    public function test_extracts_parameters(): void
    {
        $discovery = new ToolDiscovery();
        $tools = $discovery->discover([TestToolService::class]);

        $params = $tools[0]['parameters'];

        $this->assertArrayHasKey('orderId', $params);
        $this->assertEquals('integer', $params['orderId']['type']);
        $this->assertTrue($params['orderId']['required']);
    }

    public function test_generates_json_schema(): void
    {
        $discovery = new ToolDiscovery();
        $tools = $discovery->discover([TestToolService::class]);

        $schema = $tools[0]['schema'];

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('orderId', $schema['properties']);
        $this->assertContains('orderId', $schema['required']);
    }
}

// Test fixture
class TestToolService
{
    #[AsAITool('Find an order by ID')]
    public function findOrder(
        #[Rules('required|integer')] int $orderId
    ): array {
        return ['id' => $orderId];
    }

    #[AsAITool('Cancel an order')]
    public function cancelOrder(
        #[Rules('required|integer')] int $orderId,
        #[Rules('required|string')] string $reason
    ): string {
        return "Cancelled";
    }
}
