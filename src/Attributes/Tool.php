<?php

namespace LaravelAIAgent\Attributes;

use Attribute;

/**
 * Alias for AsAITool - shorter name for convenience.
 * 
 * @see AsAITool
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Tool extends AsAITool
{
    // Inherits everything from AsAITool
}
