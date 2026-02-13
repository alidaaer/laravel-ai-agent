<?php

namespace LaravelAIAgent\Tests\Feature;

use LaravelAIAgent\Http\Controllers\ChatController;
use LaravelAIAgent\Exceptions\DriverException;
use PHPUnit\Framework\TestCase;

class ChatControllerTest extends TestCase
{
    public function test_format_error_message_for_driver_exception(): void
    {
        $controller = new ChatController();
        $reflection = new \ReflectionMethod($controller, 'formatErrorMessage');
        $reflection->setAccessible(true);

        $e429 = new DriverException('Too many', null, 429);
        $this->assertStringContainsString('Rate limit', $reflection->invoke($controller, $e429));

        $e401 = new DriverException('Unauthorized', null, 401);
        $this->assertStringContainsString('Authentication', $reflection->invoke($controller, $e401));

        $e403 = new DriverException('Forbidden', null, 403);
        $this->assertStringContainsString('Access denied', $reflection->invoke($controller, $e403));
    }

    public function test_driver_exception_get_http_code(): void
    {
        $e = new DriverException('Test', null, 429);
        $this->assertEquals(429, $e->getHttpCode());

        $e0 = new DriverException('Test', null, 0);
        $this->assertEquals(500, $e0->getHttpCode());

        $e500 = new DriverException('Default');
        $this->assertEquals(500, $e500->getHttpCode());
    }
}
