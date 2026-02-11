<?php

namespace LaravelAIAgent\Tools;

use ReflectionMethod;
use ReflectionParameter;
use LaravelAIAgent\Attributes\AsAITool;

/**
 * Smart Schema Generator
 * 
 * Automatically generates tool descriptions and schemas from method signatures.
 * Supports 3 levels:
 * 1. Fully automatic (from method name and type hints)
 * 2. Custom description (from attribute)
 * 3. Full customization (custom param descriptions)
 */
class SmartSchemaGenerator
{
    /**
     * Generate a tool description from method name.
     * Converts camelCase to human-readable sentence.
     *
     * Examples:
     * - createProduct → "Create product"
     * - deleteUserById → "Delete user by id"
     * - getOrderStatus → "Get order status"
     */
    public function generateDescription(string $methodName): string
    {
        // Convert camelCase to words
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $methodName);
        
        // Capitalize first letter only
        return ucfirst(strtolower($words));
    }

    /**
     * Generate parameter description from parameter name.
     */
    public function generateParamDescription(string $paramName): string
    {
        // Convert camelCase to words
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $paramName);
        
        // Common parameter name translations (for better descriptions)
        $translations = [
            'id' => 'The ID',
            'name' => 'The name',
            'price' => 'The price',
            'email' => 'Email address',
            'phone' => 'Phone number',
            'address' => 'The address',
            'status' => 'The status',
            'quantity' => 'The quantity',
            'amount' => 'The amount',
            'date' => 'The date',
            'description' => 'The description',
            'title' => 'The title',
            'content' => 'The content',
            'message' => 'The message',
            'user' => 'The user',
            'customer' => 'The customer',
            'product' => 'The product',
            'order' => 'The order',
            'items' => 'List of items',
            'data' => 'The data',
            'options' => 'Additional options',
            'config' => 'Configuration',
            'settings' => 'Settings',
            'limit' => 'Maximum number of results',
            'offset' => 'Number of items to skip',
            'page' => 'Page number',
            'perPage' => 'Items per page',
            'sortBy' => 'Field to sort by',
            'orderBy' => 'Sort order',
            'filter' => 'Filter criteria',
            'search' => 'Search query',
            'query' => 'Search query',
            'keyword' => 'Search keyword',
            'category' => 'The category',
            'type' => 'The type',
            'active' => 'Whether active',
            'enabled' => 'Whether enabled',
            'visible' => 'Whether visible',
        ];

        $lowerName = strtolower($paramName);
        if (isset($translations[$lowerName])) {
            return $translations[$lowerName];
        }

        return ucfirst(strtolower($words));
    }

    /**
     * Map PHP type to JSON Schema type.
     */
    public function mapPhpTypeToJsonSchema(?string $phpType): string
    {
        if ($phpType === null) {
            return 'string';
        }

        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object', 'stdClass' => 'object',
            default => 'string',
        };
    }

    /**
     * Infer type from parameter name when no type hint is provided.
     * Uses common naming conventions to guess the type.
     */
    public function inferTypeFromName(string $paramName): string
    {
        $lowerName = strtolower($paramName);
        
        // Integer patterns
        $integerPatterns = [
            'id', 'count', 'quantity', 'stock', 'page', 'limit', 'offset',
            'size', 'length', 'index', 'position', 'number', 'num', 'age',
            'year', 'month', 'day', 'hour', 'minute', 'second',
        ];
        
        // Check for ID suffix (productId, userId, orderId, etc.)
        if (preg_match('/^(.+)id$/i', $paramName) || in_array($lowerName, $integerPatterns)) {
            return 'integer';
        }
        
        // Number/Float patterns
        $numberPatterns = [
            'price', 'total', 'amount', 'sum', 'cost', 'fee', 'rate',
            'percentage', 'percent', 'discount', 'tax', 'salary', 'balance',
            'weight', 'height', 'width', 'lat', 'lng', 'latitude', 'longitude',
        ];
        
        if (in_array($lowerName, $numberPatterns)) {
            return 'number';
        }
        
        // Boolean patterns (is*, has*, can*, should*, etc.)
        if (preg_match('/^(is|has|can|should|will|was|did|does|allow|enable|disable|show|hide|include|exclude|require|active|visible|enabled|disabled|published|verified|confirmed|approved|deleted|archived|locked|blocked|featured|premium|free|paid|public|private|draft|live)/i', $paramName)) {
            return 'boolean';
        }
        
        // Array patterns (plural forms)
        $arrayPatterns = [
            'items', 'products', 'orders', 'users', 'customers', 'categories',
            'tags', 'options', 'settings', 'data', 'list', 'results', 'records',
            'rows', 'entries', 'values', 'keys', 'ids', 'names', 'emails',
            'files', 'images', 'documents', 'attachments', 'permissions', 'roles',
        ];
        
        if (in_array($lowerName, $arrayPatterns) || preg_match('/s$/', $paramName) && strlen($paramName) > 3) {
            // Only treat as array if ends with 's' and is longer than 3 chars
            // to avoid matching things like "status", "class"
            $singularExceptions = ['status', 'class', 'address', 'process', 'access', 'success', 'progress'];
            if (!in_array($lowerName, $singularExceptions)) {
                return 'array';
            }
        }
        
        // Default to string
        return 'string';
    }


    /**
     * Extract type information from a ReflectionParameter.
     */
    public function extractParameterType(ReflectionParameter $param): array
    {
        $type = $param->getType();
        $paramName = $param->getName();
        
        $info = [
            'type' => 'string',
            'required' => !$param->isOptional(),
            'nullable' => false,
        ];

        if ($type !== null) {
            // Use PHP type hint
            $info['type'] = $this->mapPhpTypeToJsonSchema($type->getName());
            $info['nullable'] = $type->allowsNull();
        } else {
            // No type hint - infer from parameter name
            $info['type'] = $this->inferTypeFromName($paramName);
        }

        if ($param->isOptional() && $param->isDefaultValueAvailable()) {
            $info['default'] = $param->getDefaultValue();
        }

        return $info;
    }

    /**
     * Generate full tool definition from a method.
     */
    public function generateToolDefinition(
        string $class,
        ReflectionMethod $method,
        AsAITool $attr
    ): array {
        $methodName = $method->getName();
        
        // Tool name: custom or method name
        $name = $attr->name ?? $methodName;
        
        // Description: custom or auto-generated
        $description = $attr->description ?? $this->generateDescription($methodName);
        
        // Parameters: Try smart discovery first
        $parameters = [];
        $required = [];

        // Check if attr has custom params defined
        if (!empty($attr->params)) {
            // Use custom params from attribute
            $parameters = $this->buildFromCustomParams($attr->params);
        } else {
            // Try to discover parameters automatically
            $parameters = $this->discoverParameters($method);
        }

        // If still empty, fall back to signature extraction
        if (empty($parameters)) {
            $parameters = $this->extractFromSignature($method);
        }

        // Apply param descriptions from attribute (override)
        foreach ($parameters as $paramName => $paramInfo) {
            if (isset($attr->params[$paramName]) && is_string($attr->params[$paramName])) {
                $parameters[$paramName]['description'] = $attr->params[$paramName];
            }
        }

        // Build required array
        foreach ($parameters as $paramName => $paramInfo) {
            if (!empty($paramInfo['required'])) {
                $required[] = $paramName;
            }
        }

        // Build JSON Schema (use stdClass for empty properties to ensure JSON encodes as {} not [])
        $schema = [
            'type' => 'object',
            'properties' => empty($parameters) ? new \stdClass() : $parameters,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return [
            'name' => $name,
            'description' => $description,
            'class' => $class,
            'method' => $methodName,
            'requires_confirmation' => $attr->requiresConfirmation,
            'permission' => $attr->permission,
            'examples' => $attr->examples,
            'agents' => $attr->agents,
            'parameters' => $parameters,
            'schema' => $schema,
        ];
    }

    /**
     * Build parameters from custom attribute params.
     */
    protected function buildFromCustomParams(array $customParams): array
    {
        $parameters = [];

        foreach ($customParams as $name => $config) {
            // Parse 'name:type' format (e.g., 'id:integer', 'price:number')
            $explicitType = null;
            if (str_contains($name, ':')) {
                [$name, $explicitType] = explode(':', $name, 2);
                $explicitType = $this->mapPhpTypeToJsonSchema($explicitType);
            }

            if (is_string($config)) {
                // Simple: 'name' => 'description' or 'name:type' => 'description'
                $parameters[$name] = [
                    'type' => $explicitType ?? $this->inferTypeFromName($name),
                    'description' => $config,
                    'required' => true,
                ];
            } elseif (is_array($config)) {
                // Full config: 'name' => ['type' => 'string', 'description' => '...']
                $parameters[$name] = [
                    'type' => $explicitType ?? $config['type'] ?? $this->inferTypeFromName($name),
                    'description' => $config['description'] ?? $this->generateParamDescription($name),
                    'required' => $config['required'] ?? true,
                ];
                if (isset($config['default'])) {
                    $parameters[$name]['default'] = $config['default'];
                }
            }
        }

        return $parameters;
    }

    /**
     * Discover parameters using ParameterDiscovery.
     */
    protected function discoverParameters(ReflectionMethod $method): array
    {
        $discovery = new ParameterDiscovery();
        $discovered = $discovery->discover($method);
        
        $parameters = [];
        foreach ($discovered as $name => $config) {
            $parameters[$name] = [
                'type' => $config['type'] ?? 'string',
                'description' => $config['description'] ?? $this->generateParamDescription($name),
                'required' => $config['required'] ?? false,
            ];
            if (isset($config['default'])) {
                $parameters[$name]['default'] = $config['default'];
            }
        }

        return $parameters;
    }

    /**
     * Extract parameters from method signature (original approach).
     */
    protected function extractFromSignature(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $typeInfo = $this->extractParameterType($param);
            
            // Skip Request type parameters
            $type = $param->getType();
            if ($type && (
                $type->getName() === 'Illuminate\\Http\\Request' ||
                is_subclass_of($type->getName(), 'Illuminate\\Http\\Request')
            )) {
                continue;
            }

            $parameters[$paramName] = [
                'type' => $typeInfo['type'],
                'description' => $this->generateParamDescription($paramName),
                'required' => $typeInfo['required'] && !$typeInfo['nullable'],
                'rules' => '',
            ];

            if (isset($typeInfo['default'])) {
                $parameters[$paramName]['default'] = $typeInfo['default'];
            }
        }

        return $parameters;
    }
}
