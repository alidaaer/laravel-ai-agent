<?php

namespace LaravelAIAgent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class AsAITool
{
    /**
     * Create a new AsAITool attribute instance.
     *
     * @param string|null $description Description of what this tool does (optional - auto-generated if null)
     * @param string|null $name Custom name for the tool (defaults to method name)
     * @param array $params Custom descriptions for parameters ['paramName' => 'description']
     * @param bool $requiresConfirmation Whether to ask for confirmation before executing
     * @param string|null $permission Required permission to execute this tool
     * @param array $examples Example usages for better LLM understanding
     */
    public function __construct(
        public ?string $description = null,
        public ?string $name = null,
        public array $params = [],
        public bool $requiresConfirmation = false,
        public ?string $permission = null,
        public array $examples = [],
    ) {}
}

