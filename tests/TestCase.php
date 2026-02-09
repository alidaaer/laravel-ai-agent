<?php

namespace LaravelAIAgent\Tests;

use LaravelAIAgent\AgentServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AgentServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Agent' => \LaravelAIAgent\Facades\Agent::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai-agent.default', 'openai');
        $app['config']->set('ai-agent.drivers.openai.api_key', 'test-key');
        $app['config']->set('ai-agent.discovery.enabled', false);
    }
}
