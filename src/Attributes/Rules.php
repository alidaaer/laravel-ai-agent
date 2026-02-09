<?php

namespace LaravelAIAgent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Rules
{
    /**
     * Create a new Rules attribute instance.
     *
     * @param string|array $rules Laravel validation rules
     * @param string|null $description Description of the parameter (sent to LLM)
     * @param mixed $example Example value for better LLM understanding
     */
    public function __construct(
        public string|array $rules,
        public ?string $description = null,
        public mixed $example = null,
    ) {}
}
