<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\GraphQL;

class Executor
{
    protected Schema $schema;
    protected array $rootValue = [];
    protected array $contextValue = [];

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Execute a GraphQL query
     */
    public function execute(
        string $query,
        array $variables = [],
        ?string $operationName = null,
        array $context = []
    ): array {
        try {
            // Parse the query
            $document = $this->parse($query);
            
            // Validate the query
            $errors = $this->validate($document);
            if (!empty($errors)) {
                return ['errors' => $errors];
            }
            
            // Execute the query
            $result = $this->executeOperation(
                $document,
                $variables,
                $operationName,
                $context
            );
            
            return $result;
        } catch (\Throwable $e) {
            return [
                'errors' => [[
                    'message' => $e->getMessage(),
                    'extensions' => [
                        'category' => 'internal',
                    ],
                ]],
            ];
        }
    }

    /**
     * Parse GraphQL query
     */
    protected function parse(string $query): array
    {
        // Simple parser - in production, use a proper GraphQL parser
        $lines = explode("\n", trim($query));
        $document = [
            'definitions' => [],
        ];
        
        // Extract operation type (query, mutation, subscription)
        if (preg_match('/^(query|mutation|subscription)\s*(\w*)\s*(?:\(([^)]*)\))?\s*\{/', $query, $matches)) {
            $operation = [
                'operation' => $matches[1],
                'name' => $matches[2] ?: null,
                'variableDefinitions' => $this->parseVariables($matches[3] ?? ''),
                'selectionSet' => $this->parseSelectionSet($query),
            ];
            
            $document['definitions'][] = $operation;
        } else {
            // Default to query
            $document['definitions'][] = [
                'operation' => 'query',
                'name' => null,
                'variableDefinitions' => [],
                'selectionSet' => $this->parseSelectionSet($query),
            ];
        }
        
        return $document;
    }

    /**
     * Parse variable definitions
     */
    protected function parseVariables(string $variables): array
    {
        if (empty($variables)) {
            return [];
        }
        
        $parsed = [];
        $parts = explode(',', $variables);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/\$(\w+):\s*([\w!\[\]]+)(?:\s*=\s*(.+))?/', $part, $matches)) {
                $parsed[] = [
                    'variable' => $matches[1],
                    'type' => $matches[2],
                    'defaultValue' => $matches[3] ?? null,
                ];
            }
        }
        
        return $parsed;
    }

    /**
     * Parse selection set
     */
    protected function parseSelectionSet(string $query): array
    {
        // Extract fields between braces
        preg_match('/\{([^}]+)\}/', $query, $matches);
        if (!isset($matches[1])) {
            return [];
        }
        
        $selections = [];
        $fields = explode("\n", trim($matches[1]));
        
        foreach ($fields as $field) {
            $field = trim($field);
            if (empty($field)) {
                continue;
            }
            
            // Parse field with possible arguments and sub-selections
            if (preg_match('/^(\w+)(?:\(([^)]*)\))?(?:\s*\{([^}]+)\})?/', $field, $fieldMatches)) {
                $selection = [
                    'name' => $fieldMatches[1],
                    'arguments' => $this->parseArguments($fieldMatches[2] ?? ''),
                ];
                
                if (isset($fieldMatches[3])) {
                    $selection['selectionSet'] = $this->parseSelectionSet('{' . $fieldMatches[3] . '}');
                }
                
                $selections[] = $selection;
            }
        }
        
        return $selections;
    }

    /**
     * Parse field arguments
     */
    protected function parseArguments(string $arguments): array
    {
        if (empty($arguments)) {
            return [];
        }
        
        $parsed = [];
        $parts = explode(',', $arguments);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/(\w+):\s*(.+)/', $part, $matches)) {
                $parsed[$matches[1]] = $this->parseValue($matches[2]);
            }
        }
        
        return $parsed;
    }

    /**
     * Parse argument value
     */
    protected function parseValue(string $value): mixed
    {
        $value = trim($value);
        
        // Variable
        if (str_starts_with($value, '$')) {
            return ['variable' => substr($value, 1)];
        }
        
        // String
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }
        
        // Boolean
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        
        // Null
        if ($value === 'null') {
            return null;
        }
        
        // Number
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        
        // Array or Object - simplified
        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * Validate document
     */
    protected function validate(array $document): array
    {
        $errors = [];
        
        // Basic validation
        if (empty($document['definitions'])) {
            $errors[] = [
                'message' => 'Document does not contain any operations',
            ];
        }
        
        return $errors;
    }

    /**
     * Execute operation
     */
    protected function executeOperation(
        array $document,
        array $variables,
        ?string $operationName,
        array $context
    ): array {
        // Find the operation to execute
        $operation = null;
        foreach ($document['definitions'] as $definition) {
            if ($operationName === null || $definition['name'] === $operationName) {
                $operation = $definition;
                break;
            }
        }
        
        if (!$operation) {
            return [
                'errors' => [[
                    'message' => 'Operation not found',
                ]],
            ];
        }
        
        // Execute based on operation type
        $data = match ($operation['operation']) {
            'query' => $this->executeQuery($operation, $variables, $context),
            'mutation' => $this->executeMutation($operation, $variables, $context),
            'subscription' => $this->executeSubscription($operation, $variables, $context),
            default => null,
        };
        
        return ['data' => $data];
    }

    /**
     * Execute query
     */
    protected function executeQuery(array $operation, array $variables, array $context): ?array
    {
        return $this->executeSelectionSet(
            $operation['selectionSet'],
            $this->schema->build()['query'] ?? [],
            $variables,
            $context
        );
    }

    /**
     * Execute mutation
     */
    protected function executeMutation(array $operation, array $variables, array $context): ?array
    {
        return $this->executeSelectionSet(
            $operation['selectionSet'],
            $this->schema->build()['mutation'] ?? [],
            $variables,
            $context
        );
    }

    /**
     * Execute subscription
     */
    protected function executeSubscription(array $operation, array $variables, array $context): ?array
    {
        // Subscriptions would return an async iterator
        // For now, just return null
        return null;
    }

    /**
     * Execute selection set
     */
    protected function executeSelectionSet(
        array $selectionSet,
        array $parentType,
        array $variables,
        array $context
    ): array {
        $result = [];
        
        foreach ($selectionSet as $selection) {
            $fieldName = $selection['name'];
            
            if (!isset($parentType['fields'][$fieldName])) {
                continue;
            }
            
            $field = $parentType['fields'][$fieldName];
            $args = $this->resolveArguments($selection['arguments'], $variables);
            
            // Call resolver
            $value = null;
            if (isset($field['resolve'])) {
                $value = call_user_func(
                    $field['resolve'],
                    $this->rootValue,
                    $args,
                    $context
                );
            }
            
            // Handle sub-selections
            if (isset($selection['selectionSet']) && is_array($value)) {
                $value = $this->executeSelectionSet(
                    $selection['selectionSet'],
                    ['fields' => $value],
                    $variables,
                    $context
                );
            }
            
            $result[$fieldName] = $value;
        }
        
        return $result;
    }

    /**
     * Resolve arguments
     */
    protected function resolveArguments(array $arguments, array $variables): array
    {
        $resolved = [];
        
        foreach ($arguments as $name => $value) {
            if (is_array($value) && isset($value['variable'])) {
                $resolved[$name] = $variables[$value['variable']] ?? null;
            } else {
                $resolved[$name] = $value;
            }
        }
        
        return $resolved;
    }

    /**
     * Set root value
     */
    public function setRootValue(array $rootValue): self
    {
        $this->rootValue = $rootValue;
        return $this;
    }
}