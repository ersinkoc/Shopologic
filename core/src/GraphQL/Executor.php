<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

use Shopologic\Core\Container\ContainerInterface;

/**
 * GraphQL query executor
 */
class Executor
{
    private Schema $schema;
    private ?ContainerInterface $container;
    
    public function __construct(Schema $schema, ?ContainerInterface $container = null)
    {
        $this->schema = $schema;
        $this->container = $container;
    }
    
    /**
     * Execute GraphQL document
     */
    public function execute(
        Document $document,
        array $variables = [],
        ?string $operationName = null,
        ?Context $context = null
    ) {
        // Get operation to execute
        $operation = $this->getOperation($document, $operationName);
        
        if (!$operation) {
            throw new \Exception('No operation found');
        }
        
        // Coerce variable values
        $coercedVariables = $this->coerceVariableValues(
            $operation->variableDefinitions,
            $variables
        );
        
        // Execute operation
        switch ($operation->type) {
            case 'query':
                return $this->executeQuery($operation, $coercedVariables, $context);
                
            case 'mutation':
                return $this->executeMutation($operation, $coercedVariables, $context);
                
            case 'subscription':
                return $this->executeSubscription($operation, $coercedVariables, $context);
                
            default:
                throw new \Exception("Unknown operation type: {$operation->type}");
        }
    }
    
    /**
     * Execute query operation
     */
    private function executeQuery(
        OperationDefinition $operation,
        array $variables,
        ?Context $context
    ) {
        $rootValue = null;
        $rootType = $this->schema->getQueryType();
        
        return $this->executeSelectionSet(
            $operation->selectionSet,
            $rootType,
            $rootValue,
            $variables,
            $context
        );
    }
    
    /**
     * Execute mutation operation
     */
    private function executeMutation(
        OperationDefinition $operation,
        array $variables,
        ?Context $context
    ) {
        $rootValue = null;
        $rootType = $this->schema->getMutationType();
        
        if (!$rootType) {
            throw new \Exception('Schema does not support mutations');
        }
        
        // Execute mutations serially
        return $this->executeSelectionSet(
            $operation->selectionSet,
            $rootType,
            $rootValue,
            $variables,
            $context,
            true // Serial execution
        );
    }
    
    /**
     * Execute subscription operation
     */
    private function executeSubscription(
        OperationDefinition $operation,
        array $variables,
        ?Context $context
    ) {
        $rootType = $this->schema->getSubscriptionType();
        
        if (!$rootType) {
            throw new \Exception('Schema does not support subscriptions');
        }
        
        // For subscriptions, we return an async iterator
        // This is simplified - real implementation would need proper async support
        throw new \Exception('Subscriptions not yet implemented');
    }
    
    /**
     * Execute selection set
     */
    private function executeSelectionSet(
        SelectionSet $selectionSet,
        Type $objectType,
        $rootValue,
        array $variables,
        ?Context $context,
        bool $serial = false
    ) {
        $groupedFieldSet = $this->collectFields($objectType, $selectionSet, $variables);
        $resultMap = [];
        
        if ($serial) {
            // Execute fields serially (for mutations)
            foreach ($groupedFieldSet as $responseKey => $fields) {
                $fieldName = $fields[0]->name;
                $fieldType = $objectType->getField($fieldName);
                
                if (!$fieldType) {
                    continue;
                }
                
                $resultMap[$responseKey] = $this->executeField(
                    $objectType,
                    $rootValue,
                    $fieldType,
                    $fields,
                    $variables,
                    $context
                );
            }
        } else {
            // Execute fields in parallel (for queries)
            foreach ($groupedFieldSet as $responseKey => $fields) {
                $fieldName = $fields[0]->name;
                $fieldType = $objectType->getField($fieldName);
                
                if (!$fieldType) {
                    continue;
                }
                
                $resultMap[$responseKey] = $this->executeField(
                    $objectType,
                    $rootValue,
                    $fieldType,
                    $fields,
                    $variables,
                    $context
                );
            }
        }
        
        return $resultMap;
    }
    
    /**
     * Execute field
     */
    private function executeField(
        Type $objectType,
        $rootValue,
        Field $fieldType,
        array $fields,
        array $variables,
        ?Context $context
    ) {
        // Get field resolver
        $resolver = $fieldType->getResolver();
        
        if (!$resolver) {
            // Default resolver
            $resolver = function ($root, $args) use ($fieldType) {
                $fieldName = $fieldType->getName();
                
                if (is_array($root)) {
                    return $root[$fieldName] ?? null;
                } elseif (is_object($root)) {
                    if (property_exists($root, $fieldName)) {
                        return $root->$fieldName;
                    } elseif (method_exists($root, $fieldName)) {
                        return $root->$fieldName();
                    } elseif (method_exists($root, 'get' . ucfirst($fieldName))) {
                        $method = 'get' . ucfirst($fieldName);
                        return $root->$method();
                    }
                }
                
                return null;
            };
        }
        
        // Coerce argument values
        $argumentValues = $this->coerceArgumentValues($fieldType, $fields[0], $variables);
        
        // Execute resolver
        try {
            $result = $resolver($rootValue, $argumentValues, $context, [
                'fieldName' => $fieldType->getName(),
                'fieldNodes' => $fields,
                'returnType' => $fieldType->getType(),
                'parentType' => $objectType,
                'schema' => $this->schema,
                'fragments' => [], // TODO: Collect fragments
                'rootValue' => $rootValue,
                'operation' => null, // TODO: Pass operation
                'variableValues' => $variables
            ]);
            
            // Complete value
            return $this->completeValue(
                $fieldType->getType(),
                $fields,
                $result,
                $variables,
                $context
            );
        } catch (\Exception $e) {
            throw new ExecutionException(
                "Error resolving field {$fieldType->getName()}: " . $e->getMessage(),
                $fields[0],
                $e
            );
        }
    }
    
    /**
     * Complete value based on type
     */
    private function completeValue(
        Type $returnType,
        array $fieldNodes,
        $result,
        array $variables,
        ?Context $context
    ) {
        // Handle non-null types
        if ($returnType->isNonNull()) {
            $innerType = $returnType->getOfType();
            $completedResult = $this->completeValue(
                $innerType,
                $fieldNodes,
                $result,
                $variables,
                $context
            );
            
            if ($completedResult === null) {
                throw new \Exception('Cannot return null for non-nullable field');
            }
            
            return $completedResult;
        }
        
        // Handle null values
        if ($result === null) {
            return null;
        }
        
        // Handle list types
        if ($returnType->isList()) {
            if (!is_array($result) && !($result instanceof \Traversable)) {
                throw new \Exception('Expected array or traversable for list type');
            }
            
            $innerType = $returnType->getOfType();
            $completedResults = [];
            
            foreach ($result as $item) {
                $completedResults[] = $this->completeValue(
                    $innerType,
                    $fieldNodes,
                    $item,
                    $variables,
                    $context
                );
            }
            
            return $completedResults;
        }
        
        // Handle scalar types
        if ($returnType->isScalar()) {
            return $returnType->serialize($result);
        }
        
        // Handle enum types
        if ($returnType->isEnum()) {
            $enumValue = $returnType->getValue($result);
            if (!$enumValue) {
                throw new \Exception("Invalid enum value: {$result}");
            }
            return $enumValue->getValue();
        }
        
        // Handle object types
        if ($returnType->isObject()) {
            $subSelectionSet = $this->mergeSelectionSets($fieldNodes);
            
            if (!$subSelectionSet) {
                throw new \Exception('Field of object type must have selection set');
            }
            
            return $this->executeSelectionSet(
                $subSelectionSet,
                $returnType,
                $result,
                $variables,
                $context
            );
        }
        
        // Handle interface and union types
        if ($returnType->isInterface() || $returnType->isUnion()) {
            $resolveType = $returnType->getResolveType();
            
            if (!$resolveType) {
                throw new \Exception('Interface/Union type must provide resolveType function');
            }
            
            $resolvedType = $resolveType($result, $context, [
                'schema' => $this->schema,
                'parentType' => $returnType
            ]);
            
            if (!$resolvedType) {
                throw new \Exception('Could not resolve type');
            }
            
            return $this->completeValue(
                $resolvedType,
                $fieldNodes,
                $result,
                $variables,
                $context
            );
        }
        
        throw new \Exception("Unknown type: {$returnType->getName()}");
    }
    
    /**
     * Collect fields from selection set
     */
    private function collectFields(
        Type $objectType,
        SelectionSet $selectionSet,
        array $variables,
        array &$groupedFields = [],
        array &$visitedFragments = []
    ): array {
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof Field) {
                if (!$this->shouldIncludeNode($selection, $variables)) {
                    continue;
                }
                
                $name = $selection->alias ?? $selection->name;
                
                if (!isset($groupedFields[$name])) {
                    $groupedFields[$name] = [];
                }
                
                $groupedFields[$name][] = $selection;
            } elseif ($selection instanceof InlineFragment) {
                if (!$this->shouldIncludeNode($selection, $variables)) {
                    continue;
                }
                
                if ($selection->typeCondition && 
                    !$this->doesFragmentTypeApply($objectType, $selection->typeCondition)) {
                    continue;
                }
                
                $this->collectFields(
                    $objectType,
                    $selection->selectionSet,
                    $variables,
                    $groupedFields,
                    $visitedFragments
                );
            } elseif ($selection instanceof FragmentSpread) {
                if (!$this->shouldIncludeNode($selection, $variables)) {
                    continue;
                }
                
                $fragmentName = $selection->name;
                
                if (in_array($fragmentName, $visitedFragments)) {
                    continue;
                }
                
                // TODO: Get fragment definition and collect its fields
            }
        }
        
        return $groupedFields;
    }
    
    /**
     * Check if node should be included based on directives
     */
    private function shouldIncludeNode($node, array $variables): bool
    {
        $skip = $this->getDirectiveValue($node, 'skip', $variables);
        if ($skip === true) {
            return false;
        }
        
        $include = $this->getDirectiveValue($node, 'include', $variables);
        if ($include === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get directive value
     */
    private function getDirectiveValue($node, string $directiveName, array $variables)
    {
        foreach ($node->directives as $directive) {
            if ($directive->name === $directiveName) {
                $args = $this->coerceArgumentValues(
                    $this->schema->getDirective($directiveName),
                    $directive,
                    $variables
                );
                
                return $args['if'] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * Check if fragment type applies
     */
    private function doesFragmentTypeApply(Type $objectType, string $fragmentType): bool
    {
        if ($objectType->getName() === $fragmentType) {
            return true;
        }
        
        if ($objectType->isObject()) {
            foreach ($objectType->getInterfaces() as $interface) {
                if ($interface->getName() === $fragmentType) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Merge selection sets from field nodes
     */
    private function mergeSelectionSets(array $fieldNodes): ?SelectionSet
    {
        $selections = [];
        
        foreach ($fieldNodes as $fieldNode) {
            if ($fieldNode->selectionSet) {
                $selections = array_merge($selections, $fieldNode->selectionSet->selections);
            }
        }
        
        if (empty($selections)) {
            return null;
        }
        
        return new SelectionSet($selections);
    }
    
    /**
     * Get operation from document
     */
    private function getOperation(Document $document, ?string $operationName): ?OperationDefinition
    {
        $operations = $document->getOperations();
        
        if (!$operationName) {
            if (count($operations) !== 1) {
                throw new \Exception('Must provide operation name if query contains multiple operations');
            }
            
            return reset($operations);
        }
        
        foreach ($operations as $operation) {
            if ($operation->name === $operationName) {
                return $operation;
            }
        }
        
        return null;
    }
    
    /**
     * Coerce variable values
     */
    private function coerceVariableValues(array $variableDefinitions, array $values): array
    {
        $coercedValues = [];
        
        foreach ($variableDefinitions as $varDef) {
            $varName = $varDef->name;
            $varType = $this->typeFromAST($varDef->type);
            
            if (!isset($values[$varName])) {
                if ($varDef->defaultValue) {
                    $coercedValues[$varName] = $this->valueFromAST($varDef->defaultValue, $varType);
                } elseif ($varType->isNonNull()) {
                    throw new \Exception("Variable \${$varName} of required type {$varType->getName()} was not provided");
                }
            } else {
                $value = $values[$varName];
                $coercedValue = $this->coerceValue($value, $varType);
                
                if ($coercedValue === null && $varType->isNonNull()) {
                    throw new \Exception("Variable \${$varName} of non-null type {$varType->getName()} must not be null");
                }
                
                $coercedValues[$varName] = $coercedValue;
            }
        }
        
        return $coercedValues;
    }
    
    /**
     * Coerce argument values
     */
    private function coerceArgumentValues($field, $node, array $variables): array
    {
        $coercedValues = [];
        $argDefs = $field->getArgs();
        
        foreach ($argDefs as $argDef) {
            $argName = $argDef->getName();
            $argType = $argDef->getType();
            $argumentNode = null;
            
            foreach ($node->arguments as $arg) {
                if ($arg->name === $argName) {
                    $argumentNode = $arg;
                    break;
                }
            }
            
            if (!$argumentNode) {
                if ($argDef->hasDefaultValue()) {
                    $coercedValues[$argName] = $argDef->getDefaultValue();
                } elseif ($argType->isNonNull()) {
                    throw new \Exception("Argument {$argName} of required type {$argType->getName()} was not provided");
                }
            } else {
                $value = $this->valueFromAST($argumentNode->value, $argType, $variables);
                
                if ($value === null && $argType->isNonNull()) {
                    throw new \Exception("Argument {$argName} of non-null type {$argType->getName()} must not be null");
                }
                
                $coercedValues[$argName] = $value;
            }
        }
        
        return $coercedValues;
    }
    
    /**
     * Convert AST type to schema type
     */
    private function typeFromAST(TypeNode $typeNode): Type
    {
        if ($typeNode instanceof ListTypeNode) {
            return Type::list($this->typeFromAST($typeNode->type));
        }
        
        if ($typeNode instanceof NonNullTypeNode) {
            return Type::nonNull($this->typeFromAST($typeNode->type));
        }
        
        if ($typeNode instanceof NamedTypeNode) {
            $type = $this->schema->getType($typeNode->name);
            
            if (!$type) {
                throw new \Exception("Unknown type: {$typeNode->name}");
            }
            
            return $type;
        }
        
        throw new \Exception("Unknown type node");
    }
    
    /**
     * Get value from AST
     */
    private function valueFromAST(Value $valueNode, Type $type, array $variables = [])
    {
        if ($valueNode instanceof Variable) {
            $varName = $valueNode->name;
            
            if (!array_key_exists($varName, $variables)) {
                return null;
            }
            
            return $variables[$varName];
        }
        
        if ($type->isNonNull()) {
            return $this->valueFromAST($valueNode, $type->getOfType(), $variables);
        }
        
        if ($valueNode instanceof NullValue) {
            return null;
        }
        
        if ($type->isList()) {
            if ($valueNode instanceof ListValue) {
                $innerType = $type->getOfType();
                $coercedValues = [];
                
                foreach ($valueNode->values as $itemNode) {
                    $coercedValues[] = $this->valueFromAST($itemNode, $innerType, $variables);
                }
                
                return $coercedValues;
            }
            
            // Single value for list type
            return [$this->valueFromAST($valueNode, $type->getOfType(), $variables)];
        }
        
        if ($type->isInputObject()) {
            if (!($valueNode instanceof ObjectValue)) {
                return null;
            }
            
            $coercedValues = [];
            $fields = $type->getFields();
            
            foreach ($fields as $field) {
                $fieldName = $field->getName();
                $fieldNode = null;
                
                foreach ($valueNode->fields as $objectField) {
                    if ($objectField->name === $fieldName) {
                        $fieldNode = $objectField;
                        break;
                    }
                }
                
                if ($fieldNode) {
                    $fieldValue = $this->valueFromAST(
                        $fieldNode->value,
                        $field->getType(),
                        $variables
                    );
                    $coercedValues[$fieldName] = $fieldValue;
                } elseif ($field->hasDefaultValue()) {
                    $coercedValues[$fieldName] = $field->getDefaultValue();
                }
            }
            
            return $coercedValues;
        }
        
        if ($type->isScalar()) {
            return $type->parseLiteral($valueNode);
        }
        
        if ($type->isEnum()) {
            if (!($valueNode instanceof EnumValue)) {
                return null;
            }
            
            $enumValue = $type->getValue($valueNode->value);
            return $enumValue ? $enumValue->getValue() : null;
        }
        
        throw new \Exception("Unknown type for valueFromAST: {$type->getName()}");
    }
    
    /**
     * Coerce value to type
     */
    private function coerceValue($value, Type $type)
    {
        if ($type->isNonNull()) {
            if ($value === null) {
                throw new \Exception("Expected non-null value");
            }
            
            return $this->coerceValue($value, $type->getOfType());
        }
        
        if ($value === null) {
            return null;
        }
        
        if ($type->isList()) {
            if (!is_array($value)) {
                $value = [$value];
            }
            
            $innerType = $type->getOfType();
            $coercedValues = [];
            
            foreach ($value as $item) {
                $coercedValues[] = $this->coerceValue($item, $innerType);
            }
            
            return $coercedValues;
        }
        
        if ($type->isInputObject()) {
            if (!is_array($value) && !is_object($value)) {
                throw new \Exception("Expected object for input object type");
            }
            
            $coercedValues = [];
            $fields = $type->getFields();
            
            foreach ($fields as $field) {
                $fieldName = $field->getName();
                $fieldValue = is_array($value) ? ($value[$fieldName] ?? null) : ($value->$fieldName ?? null);
                
                if ($fieldValue === null && $field->hasDefaultValue()) {
                    $fieldValue = $field->getDefaultValue();
                }
                
                $coercedValues[$fieldName] = $this->coerceValue($fieldValue, $field->getType());
            }
            
            return $coercedValues;
        }
        
        if ($type->isScalar()) {
            return $type->parseValue($value);
        }
        
        if ($type->isEnum()) {
            $enumValue = $type->getValue($value);
            return $enumValue ? $enumValue->getValue() : null;
        }
        
        return $value;
    }
}

/**
 * Execution exception
 */
class ExecutionException extends \Exception
{
    private $data;
    private array $errors = [];
    
    public function __construct(string $message, $node = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        
        $this->errors[] = [
            'message' => $message,
            'locations' => $node ? [['line' => $node->line ?? 0, 'column' => $node->column ?? 0]] : [],
            'path' => [] // TODO: Track path
        ];
    }
    
    public function getData()
    {
        return $this->data;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}