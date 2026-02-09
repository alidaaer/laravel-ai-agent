<?php

namespace LaravelAIAgent\Tools;

use Illuminate\Support\Facades\Validator;
use LaravelAIAgent\Exceptions\ToolValidationException;

class ToolValidator
{
    /**
     * Validate arguments against tool parameters.
     *
     * @param array $tool The tool definition
     * @param array $arguments The arguments from LLM
     * @throws ToolValidationException
     */
    public function validate(array $tool, array $arguments): array
    {
        $rules = $this->buildValidationRules($tool['parameters']);
        
        $validator = Validator::make($arguments, $rules);

        if ($validator->fails()) {
            throw new ToolValidationException(
                "Validation failed for tool '{$tool['name']}': " . $validator->errors()->first(),
                $validator->errors()->toArray()
            );
        }

        return $validator->validated();
    }

    /**
     * Build Laravel validation rules from tool parameters.
     */
    protected function buildValidationRules(array $parameters): array
    {
        $rules = [];

        foreach ($parameters as $name => $param) {
            $paramRules = [];

            // Required or nullable
            if ($param['required']) {
                $paramRules[] = 'required';
            } else {
                $paramRules[] = 'nullable';
            }

            // Type validation
            $paramRules[] = match ($param['type']) {
                'integer' => 'integer',
                'number' => 'numeric',
                'boolean' => 'boolean',
                'array' => 'array',
                default => 'string',
            };

            // Add custom rules if defined
            if (!empty($param['rules'])) {
                if (is_string($param['rules'])) {
                    $paramRules = array_merge($paramRules, explode('|', $param['rules']));
                } elseif (is_array($param['rules'])) {
                    $paramRules = array_merge($paramRules, $param['rules']);
                }
            }

            $rules[$name] = array_unique($paramRules);
        }

        return $rules;
    }
}
