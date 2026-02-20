<?php

namespace LaravelAIAgent\Tools;

use ReflectionMethod;
use ReflectionParameter;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Smart Parameter Discovery
 * 
 * Discovers tool parameters from multiple sources:
 * 1. Method signature (explicit parameters)
 * 2. FormRequest::rules() 
 * 3. Static analysis of method body (Request::input, get, etc.)
 * 4. Eloquent Model::$fillable
 */
class ParameterDiscovery
{
    /**
     * Request method patterns to search for.
     */
    protected array $requestPatterns = [
        // $request->input('name') or $request->input("name")
        '/\$request\s*->\s*input\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[^)]+)?\)/i',
        
        // $request->get('name')
        '/\$request\s*->\s*get\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[^)]+)?\)/i',
        
        // $request->validated('name') or $request->validated()['name']
        '/\$request\s*->\s*validated\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
        
        // $request->only(['name', 'price'])
        '/\$request\s*->\s*only\s*\(\s*\[([^\]]+)\]\s*\)/i',
        
        // $request['name']
        '/\$request\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/i',
        
        // $request->name (dynamic property)
        '/\$request\s*->\s*([a-z_][a-z0-9_]*)\s*(?:[;,\)]|$)/i',
        
        // request('name') helper
        '/\brequest\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[^)]+)?\)/i',
    ];

    /**
     * Reserved/excluded parameter names.
     */
    protected array $excludedNames = [
        'input', 'get', 'validated', 'only', 'except', 'all', 'has', 'hasAny',
        'filled', 'missing', 'boolean', 'integer', 'float', 'string', 'date',
        'file', 'files', 'header', 'headers', 'server', 'cookie', 'cookies',
        'session', 'user', 'route', 'url', 'fullUrl', 'path', 'method',
        'isMethod', 'ajax', 'pjax', 'json', 'query', 'post', 'merge',
        'replace', 'flash', 'old', 'flush', 'offsetGet', 'offsetSet',
    ];

    /**
     * Discover parameters for a method.
     */
    public function discover(ReflectionMethod $method): array
    {
        $parameters = [];

        // 1. First, try explicit method parameters
        $explicitParams = $this->discoverFromSignature($method);
        if (!empty($explicitParams)) {
            return $explicitParams;
        }

        // 2. Check for FormRequest parameter
        $formRequestParams = $this->discoverFromFormRequest($method);
        if (!empty($formRequestParams)) {
            return $formRequestParams;
        }

        // 3. Static analysis of method body
        $bodyParams = $this->discoverFromMethodBody($method);
        if (!empty($bodyParams)) {
            return $bodyParams;
        }

        return $parameters;
    }

    /**
     * Discover parameters from method signature.
     */
    protected function discoverFromSignature(ReflectionMethod $method): array
    {
        $parameters = [];
        
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type ? $type->getName() : null;

            // Skip Request, FormRequest, Model types
            if ($this->isSkippableType($typeName)) {
                continue;
            }

            $parameters[$param->getName()] = [
                'type' => $this->phpTypeToJsonType($typeName),
                'required' => !$param->isOptional(),
                'default' => $param->isOptional() ? $param->getDefaultValue() : null,
                'description' => $this->generateDescription($param->getName()),
            ];
        }

        return $parameters;
    }

    /**
     * Discover parameters from FormRequest::rules().
     */
    protected function discoverFromFormRequest(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if (!$type) continue;

            $typeName = $type->getName();

            // Check if it's a FormRequest subclass
            if (class_exists($typeName) && is_subclass_of($typeName, FormRequest::class)) {
                try {
                    $formRequest = new $typeName();
                    
                    if (method_exists($formRequest, 'rules')) {
                        $rules = $formRequest->rules();
                        $parameters = $this->parseValidationRules($rules);
                    }
                } catch (\Throwable $e) {
                    // FormRequest might need dependencies, skip
                }
            }
        }

        return $parameters;
    }

    /**
     * Discover parameters from method body using static analysis.
     */
    protected function discoverFromMethodBody(ReflectionMethod $method): array
    {
        $parameters = [];
        
        // Get method source code
        $source = $this->getMethodSource($method);
        if (!$source) {
            return $parameters;
        }

        // Check if method has Request parameter
        $hasRequest = false;
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if ($type && ($type->getName() === Request::class || 
                         is_subclass_of($type->getName(), Request::class))) {
                $hasRequest = true;
                break;
            }
        }

        if (!$hasRequest) {
            return $parameters;
        }

        // Apply all patterns
        foreach ($this->requestPatterns as $pattern) {
            if (preg_match_all($pattern, $source, $matches)) {
                foreach ($matches[1] as $match) {
                    // Handle array syntax from only()
                    if (strpos($match, ',') !== false || strpos($match, "'") !== false) {
                        // Parse: 'name', 'price' or "name", "price"
                        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $match, $arrayMatches);
                        foreach ($arrayMatches[1] as $name) {
                            $this->addParameter($parameters, $name, $source);
                        }
                    } else {
                        $this->addParameter($parameters, $match, $source);
                    }
                }
            }
        }

        return $parameters;
    }

    /**
     * Add a parameter if it's valid.
     */
    protected function addParameter(array &$parameters, string $name, string $source): void
    {
        $name = trim($name);
        
        // Skip if empty, excluded, or already exists
        if (empty($name) || in_array(strtolower($name), $this->excludedNames) || isset($parameters[$name])) {
            return;
        }

        // Skip if it looks like a method call (camelCase followed by parenthesis in source)
        if (preg_match('/^[a-z]+[A-Z]/', $name) && preg_match('/\\$request\\s*->\\s*' . preg_quote($name) . '\\s*\\(/', $source)) {
            return;
        }

        $parameters[$name] = [
            'type' => $this->inferTypeFromContext($name, $source),
            'required' => $this->inferRequired($name, $source),
            'description' => $this->generateDescription($name),
        ];
    }

    /**
     * Get the source code of a method.
     */
    protected function getMethodSource(ReflectionMethod $method): ?string
    {
        $filename = $method->getFileName();
        if (!$filename || !file_exists($filename)) {
            return null;
        }

        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        
        $lines = file($filename);
        $source = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        
        return $source;
    }

    /**
     * Parse Laravel validation rules to parameter definitions.
     */
    protected function parseValidationRules(array $rules): array
    {
        $parameters = [];

        foreach ($rules as $field => $rule) {
            // Skip nested rules like 'items.*'
            if (strpos($field, '.') !== false && strpos($field, '*.') !== false) {
                continue;
            }

            $ruleString = is_array($rule) ? implode('|', $rule) : $rule;
            
            $parameters[$field] = [
                'type' => $this->inferTypeFromRules($ruleString),
                'required' => $this->isRequiredRule($ruleString),
                'description' => $this->generateDescription($field),
            ];
        }

        return $parameters;
    }

    /**
     * Infer JSON type from Laravel validation rules.
     */
    protected function inferTypeFromRules(string $rules): string
    {
        $rules = strtolower($rules);

        if (str_contains($rules, 'integer') || str_contains($rules, 'numeric')) {
            return 'number';
        }
        if (str_contains($rules, 'boolean') || str_contains($rules, 'bool')) {
            return 'boolean';
        }
        if (str_contains($rules, 'array')) {
            return 'array';
        }
        if (str_contains($rules, 'email') || str_contains($rules, 'string') || str_contains($rules, 'url')) {
            return 'string';
        }

        return 'string';
    }

    /**
     * Check if rule contains 'required'.
     */
    protected function isRequiredRule(string $rules): bool
    {
        return str_contains(strtolower($rules), 'required') && 
               !str_contains(strtolower($rules), 'required_if') &&
               !str_contains(strtolower($rules), 'required_unless');
    }

    /**
     * Infer type from parameter name and context.
     */
    protected function inferTypeFromContext(string $name, string $source): string
    {
        $lowerName = strtolower($name);

        // String patterns (check first - these are explicitly strings)
        $stringPatterns = [
            'name', 'title', 'description', 'content', 'text', 'message', 'note', 'notes',
            'comment', 'reason', 'address', 'email', 'phone', 'url', 'link', 'path',
            'slug', 'code', 'token', 'password', 'username', 'label', 'caption',
            'summary', 'body', 'subject', 'type', 'status', 'category', 'color',
        ];
        
        foreach ($stringPatterns as $pattern) {
            // Match exact or compound names like "discount_reason", "shipping_address"
            if ($lowerName === $pattern || str_ends_with($lowerName, '_' . $pattern) || str_ends_with($lowerName, $pattern)) {
                return 'string';
            }
        }

        // Number patterns - only if name ends with these (more precise)
        $numberSuffixes = ['_id', '_price', '_amount', '_quantity', '_qty', '_total', '_count', '_number', '_num', '_age', '_year', '_limit', '_offset', '_page', '_stock'];
        foreach ($numberSuffixes as $suffix) {
            if (str_ends_with($lowerName, $suffix)) {
                return 'number';
            }
        }
        
        // Exact number names
        $exactNumbers = ['id', 'price', 'amount', 'quantity', 'qty', 'total', 'count', 'number', 'num', 'age', 'year', 'month', 'day', 'hour', 'minute', 'second', 'limit', 'offset', 'page', 'stock'];
        if (in_array($lowerName, $exactNumbers)) {
            return 'number';
        }

        // Boolean patterns
        if (preg_match('/^(is_|has_|can_|should_|enable|disable|active|visible|published|approved|verified|confirmed)/', $lowerName)) {
            return 'boolean';
        }

        // Array patterns
        $arrayPatterns = ['items', 'tags', 'categories', 'ids', 'list', 'data', 'options', 'settings', 'filters'];
        if (in_array($lowerName, $arrayPatterns)) {
            return 'array';
        }

        // Check if used with boolean() method in source
        if (preg_match("/->boolean\\s*\\(['\"]" . preg_quote($name) . "['\"]\\)/i", $source)) {
            return 'boolean';
        }

        // Check if used with integer() method
        if (preg_match("/->integer\\s*\\(['\"]" . preg_quote($name) . "['\"]\\)/i", $source)) {
            return 'number';
        }

        return 'string';
    }

    /**
     * Infer if parameter is required from context.
     */
    protected function inferRequired(string $name, string $source): bool
    {
        // Check for validation patterns
        if (preg_match("/['\"]" . preg_quote($name) . "['\"]\\s*=>\\s*['\"][^'\"]*required/i", $source)) {
            return true;
        }

        // Common required field names
        $requiredNames = ['name', 'title', 'email', 'password', 'id'];
        if (in_array(strtolower($name), $requiredNames)) {
            return true;
        }

        return false;
    }

    /**
     * Check if type should be skipped.
     */
    protected function isSkippableType(?string $typeName): bool
    {
        if (!$typeName) {
            return false;
        }

        $skippableTypes = [
            Request::class,
            FormRequest::class,
            'Illuminate\Http\Request',
            'Illuminate\Foundation\Http\FormRequest',
        ];

        // Check exact match
        if (in_array($typeName, $skippableTypes)) {
            return true;
        }

        // Check if it's a subclass of Request
        if (class_exists($typeName)) {
            if (is_subclass_of($typeName, Request::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert PHP type to JSON Schema type.
     */
    protected function phpTypeToJsonType(?string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer', 'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object', 'stdClass' => 'object',
            default => 'string',
        };
    }

    /**
     * Generate description from parameter name.
     */
    protected function generateDescription(string $name): string
    {
        // Convert snake_case or camelCase to readable text
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
        $words = str_replace('_', ' ', $words);
        $words = ucfirst(strtolower($words));

        return $words;
    }
}
