<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

/**
 * GraphQL query complexity calculator
 */
class ComplexityCalculator
{
    private Schema $schema;
    private array $complexityFunctions = [];
    
    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }
    
    /**
     * Calculate complexity for a document
     */
    public function calculate(Document $document, array $variables = []): int
    {
        $totalComplexity = 0;
        
        foreach ($document->getOperations() as $operation) {
            $rootType = $this->getRootType($operation);
            
            if ($rootType) {
                $totalComplexity += $this->calculateSelectionSetComplexity(
                    $operation->selectionSet,
                    $rootType,
                    $variables
                );
            }
        }
        
        return $totalComplexity;
    }
    
    /**
     * Register custom complexity function for a field
     */
    public function registerComplexityFunction(string $typeName, string $fieldName, callable $fn): void
    {
        if (!isset($this->complexityFunctions[$typeName])) {
            $this->complexityFunctions[$typeName] = [];
        }
        
        $this->complexityFunctions[$typeName][$fieldName] = $fn;
    }
    
    /**
     * Calculate complexity for a selection set
     */
    private function calculateSelectionSetComplexity(
        SelectionSet $selectionSet,
        Type $parentType,
        array $variables,
        int $depth = 0
    ): int {
        $complexity = 0;
        
        // Group fields by response key
        $fields = $this->collectFields($selectionSet, $parentType, $variables);
        
        foreach ($fields as $responseKey => $fieldNodes) {
            $fieldNode = $fieldNodes[0]; // Use first field node
            $fieldType = $this->getFieldType($parentType, $fieldNode->name);
            
            if (!$fieldType) {
                continue;
            }
            
            // Calculate field complexity
            $fieldComplexity = $this->calculateFieldComplexity(
                $parentType,
                $fieldNode,
                $fieldType,
                $variables,
                $depth
            );
            
            $complexity += $fieldComplexity;
        }
        
        return $complexity;
    }
    
    /**
     * Calculate complexity for a single field
     */
    private function calculateFieldComplexity(
        Type $parentType,
        Field $fieldNode,
        $fieldDef,
        array $variables,
        int $depth
    ): int {
        // Check for custom complexity function
        $customComplexity = $this->getCustomComplexity($parentType, $fieldNode, $variables);
        if ($customComplexity !== null) {
            return $customComplexity;
        }
        
        // Base complexity for the field
        $complexity = 1;
        
        // Get multipliers from arguments
        $multiplier = $this->getMultiplier($fieldNode, $variables);
        $complexity *= $multiplier;
        
        // Add depth factor
        $depthFactor = 1 + ($depth * 0.1);
        $complexity = (int)($complexity * $depthFactor);
        
        // Add complexity for nested selections
        if ($fieldNode->selectionSet) {
            $fieldType = $this->unwrapType($fieldDef->getType());
            
            if ($fieldType && ($fieldType->isObject() || $fieldType->isInterface() || $fieldType->isUnion())) {
                $nestedComplexity = $this->calculateSelectionSetComplexity(
                    $fieldNode->selectionSet,
                    $fieldType,
                    $variables,
                    $depth + 1
                );
                
                $complexity += $nestedComplexity * $multiplier;
            }
        }
        
        return $complexity;
    }
    
    /**
     * Get custom complexity for a field
     */
    private function getCustomComplexity(Type $parentType, Field $fieldNode, array $variables): ?int
    {
        $typeName = $parentType->getName();
        $fieldName = $fieldNode->name;
        
        if (isset($this->complexityFunctions[$typeName][$fieldName])) {
            $fn = $this->complexityFunctions[$typeName][$fieldName];
            $args = $this->getArgumentValues($fieldNode, $variables);
            
            return $fn($args, $variables);
        }
        
        return null;
    }
    
    /**
     * Get multiplier from field arguments
     */
    private function getMultiplier(Field $fieldNode, array $variables): int
    {
        $args = $this->getArgumentValues($fieldNode, $variables);
        $multiplier = 1;
        
        // Common pagination arguments
        if (isset($args['first'])) {
            $multiplier = max(1, (int)$args['first']);
        } elseif (isset($args['last'])) {
            $multiplier = max(1, (int)$args['last']);
        } elseif (isset($args['limit'])) {
            $multiplier = max(1, (int)$args['limit']);
        } elseif (isset($args['take'])) {
            $multiplier = max(1, (int)$args['take']);
        }
        
        // Cap multiplier to prevent abuse
        return min($multiplier, 100);
    }
    
    /**
     * Get argument values from field node
     */
    private function getArgumentValues(Field $fieldNode, array $variables): array
    {
        $values = [];
        
        foreach ($fieldNode->arguments as $arg) {
            $values[$arg->name] = $this->getValueFromAST($arg->value, $variables);
        }
        
        return $values;
    }
    
    /**
     * Get value from AST node
     */
    private function getValueFromAST($valueNode, array $variables)
    {
        if ($valueNode instanceof Variable) {
            return $variables[$valueNode->name] ?? null;
        }
        
        if ($valueNode instanceof IntValue) {
            return $valueNode->value;
        }
        
        if ($valueNode instanceof FloatValue) {
            return $valueNode->value;
        }
        
        if ($valueNode instanceof StringValue) {
            return $valueNode->value;
        }
        
        if ($valueNode instanceof BooleanValue) {
            return $valueNode->value;
        }
        
        if ($valueNode instanceof NullValue) {
            return null;
        }
        
        if ($valueNode instanceof EnumValue) {
            return $valueNode->value;
        }
        
        if ($valueNode instanceof ListValue) {
            $list = [];
            foreach ($valueNode->values as $item) {
                $list[] = $this->getValueFromAST($item, $variables);
            }
            return $list;
        }
        
        if ($valueNode instanceof ObjectValue) {
            $object = [];
            foreach ($valueNode->fields as $field) {
                $object[$field->name] = $this->getValueFromAST($field->value, $variables);
            }
            return $object;
        }
        
        return null;
    }
    
    /**
     * Collect fields from selection set
     */
    private function collectFields(
        SelectionSet $selectionSet,
        Type $parentType,
        array $variables
    ): array {
        $fields = [];
        
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof Field) {
                if (!$this->shouldIncludeNode($selection, $variables)) {
                    continue;
                }
                
                $responseKey = $selection->alias ?? $selection->name;
                
                if (!isset($fields[$responseKey])) {
                    $fields[$responseKey] = [];
                }
                
                $fields[$responseKey][] = $selection;
            } elseif ($selection instanceof InlineFragment) {
                if (!$this->shouldIncludeNode($selection, $variables)) {
                    continue;
                }
                
                if (!$selection->typeCondition || 
                    $this->doesFragmentTypeApply($parentType, $selection->typeCondition)) {
                    $inlineFields = $this->collectFields(
                        $selection->selectionSet,
                        $parentType,
                        $variables
                    );
                    
                    foreach ($inlineFields as $key => $fieldNodes) {
                        if (!isset($fields[$key])) {
                            $fields[$key] = [];
                        }
                        $fields[$key] = array_merge($fields[$key], $fieldNodes);
                    }
                }
            } elseif ($selection instanceof FragmentSpread) {
                // Would need to look up fragment definition
            }
        }
        
        return $fields;
    }
    
    /**
     * Check if node should be included based on directives
     */
    private function shouldIncludeNode($node, array $variables): bool
    {
        foreach ($node->directives as $directive) {
            if ($directive->name === 'skip') {
                $args = $this->getDirectiveArgumentValues($directive, $variables);
                if ($args['if'] === true) {
                    return false;
                }
            }
            
            if ($directive->name === 'include') {
                $args = $this->getDirectiveArgumentValues($directive, $variables);
                if ($args['if'] === false) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get directive argument values
     */
    private function getDirectiveArgumentValues(DirectiveNode $directive, array $variables): array
    {
        $values = [];
        
        foreach ($directive->arguments as $arg) {
            $values[$arg->name] = $this->getValueFromAST($arg->value, $variables);
        }
        
        return $values;
    }
    
    /**
     * Check if fragment type applies to parent type
     */
    private function doesFragmentTypeApply(Type $parentType, string $fragmentType): bool
    {
        if ($parentType->getName() === $fragmentType) {
            return true;
        }
        
        if ($parentType->isObject()) {
            foreach ($parentType->getInterfaces() as $interface) {
                if ($interface->getName() === $fragmentType) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get root type for operation
     */
    private function getRootType(OperationDefinition $operation): ?Type
    {
        switch ($operation->type) {
            case 'query':
                return $this->schema->getQueryType();
            case 'mutation':
                return $this->schema->getMutationType();
            case 'subscription':
                return $this->schema->getSubscriptionType();
            default:
                return null;
        }
    }
    
    /**
     * Get field type from parent type
     */
    private function getFieldType(Type $parentType, string $fieldName)
    {
        if ($parentType->isObject() || $parentType->isInterface()) {
            return $parentType->getField($fieldName);
        }
        
        return null;
    }
    
    /**
     * Unwrap type (remove list/non-null wrappers)
     */
    private function unwrapType(Type $type): Type
    {
        while ($type->isNonNull() || $type->isList()) {
            $type = $type->getOfType();
        }
        
        return $type;
    }
}