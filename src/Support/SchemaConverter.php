<?php

namespace LaravelAIAgent\Support;

class SchemaConverter
{
    /**
     * Type mapping from PHP/Laravel to JSON Schema.
     */
    protected array $typeMap = [
        'string' => 'string',
        'integer' => 'integer',
        'int' => 'integer',
        'number' => 'number',
        'float' => 'number',
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'array' => 'array',
    ];

    /**
     * Convert parameters to JSON Schema.
     */
    public function convert(array $parameters): array
    {
        $properties = [];
        $required = [];

        foreach ($parameters as $name => $param) {
            $properties[$name] = $this->convertParameter($name, $param);

            if ($param['required']) {
                $required[] = $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Convert a single parameter to JSON Schema.
     */
    protected function convertParameter(string $name, array $param): array
    {
        $schema = [
            'type' => $this->typeMap[$param['type']] ?? 'string',
            'description' => $param['description'] ?: ucfirst($name),
        ];

        // Parse validation rules
        $rules = $this->parseRules($param['rules']);

        // Apply rules to schema
        foreach ($rules as $rule => $value) {
            $this->applyRule($schema, $rule, $value);
        }

        // Add example if provided
        if ($param['example'] !== null) {
            $schema['example'] = $param['example'];
        }

        // Add default if provided
        if ($param['default'] !== null) {
            $schema['default'] = $param['default'];
        }

        return $schema;
    }

    /**
     * Parse Laravel validation rules.
     */
    protected function parseRules(string|array $rules): array
    {
        if (is_array($rules)) {
            return $this->parseArrayRules($rules);
        }

        if (empty($rules)) {
            return [];
        }

        $parsed = [];
        $rulesList = explode('|', $rules);

        foreach ($rulesList as $rule) {
            if (str_contains($rule, ':')) {
                [$name, $value] = explode(':', $rule, 2);
                $parsed[$name] = $value;
            } else {
                $parsed[$rule] = true;
            }
        }

        return $parsed;
    }

    /**
     * Parse array-based rules.
     */
    protected function parseArrayRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                if (str_contains($rule, ':')) {
                    [$name, $value] = explode(':', $rule, 2);
                    $parsed[$name] = $value;
                } else {
                    $parsed[$rule] = true;
                }
            }
        }

        return $parsed;
    }

    /**
     * Apply a validation rule to schema.
     */
    protected function applyRule(array &$schema, string $rule, mixed $value): void
    {
        switch ($rule) {
            case 'min':
                if ($schema['type'] === 'string') {
                    $schema['minLength'] = (int) $value;
                } elseif ($schema['type'] === 'integer' || $schema['type'] === 'number') {
                    $schema['minimum'] = (float) $value;
                } elseif ($schema['type'] === 'array') {
                    $schema['minItems'] = (int) $value;
                }
                break;

            case 'max':
                if ($schema['type'] === 'string') {
                    $schema['maxLength'] = (int) $value;
                } elseif ($schema['type'] === 'integer' || $schema['type'] === 'number') {
                    $schema['maximum'] = (float) $value;
                } elseif ($schema['type'] === 'array') {
                    $schema['maxItems'] = (int) $value;
                }
                break;

            case 'in':
                $schema['enum'] = explode(',', $value);
                break;

            case 'email':
                $schema['format'] = 'email';
                break;

            case 'url':
                $schema['format'] = 'uri';
                break;

            case 'date':
                $schema['format'] = 'date';
                break;

            case 'uuid':
                $schema['format'] = 'uuid';
                break;

            case 'regex':
                $schema['pattern'] = trim($value, '/');
                break;

            case 'integer':
                $schema['type'] = 'integer';
                break;

            case 'numeric':
                $schema['type'] = 'number';
                break;

            case 'boolean':
                $schema['type'] = 'boolean';
                break;

            case 'array':
                $schema['type'] = 'array';
                break;
        }
    }
}
