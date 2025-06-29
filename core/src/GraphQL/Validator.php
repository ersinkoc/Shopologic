<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

/**
 * GraphQL query validator
 */
class Validator
{
    private Schema $schema;
    
    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }
    
    /**
     * Validate document against schema with given rules
     */
    public function validate(Document $document, array $rules): array
    {
        $errors = [];
        $context = new ValidationContext($this->schema, $document);
        
        foreach ($rules as $rule) {
            $ruleErrors = $rule->validate($context);
            $errors = array_merge($errors, $ruleErrors);
        }
        
        return $errors;
    }
}

/**
 * Validation context
 */
class ValidationContext
{
    private Schema $schema;
    private Document $document;
    private array $fragments = [];
    private array $variableUsages = [];
    private array $recursiveVariableUsages = [];
    
    public function __construct(Schema $schema, Document $document)
    {
        $this->schema = $schema;
        $this->document = $document;
        
        // Collect fragments
        foreach ($document->getFragments() as $fragment) {
            $this->fragments[$fragment->name] = $fragment;
        }
    }
    
    public function getSchema(): Schema
    {
        return $this->schema;
    }
    
    public function getDocument(): Document
    {
        return $this->document;
    }
    
    public function getFragment(string $name): ?FragmentDefinition
    {
        return $this->fragments[$name] ?? null;
    }
    
    public function getFragments(): array
    {
        return $this->fragments;
    }
    
    public function getType(string $name): ?Type
    {
        return $this->schema->getType($name);
    }
    
    public function reportError(string $message, $node = null): array
    {
        return [[
            'message' => $message,
            'locations' => $node ? [['line' => $node->line ?? 0, 'column' => $node->column ?? 0]] : []
        ]];
    }
}

/**
 * Base validation rule
 */
abstract class ValidationRule
{
    abstract public function validate(ValidationContext $context): array;
}

/**
 * Validation rules namespace
 */
namespace Shopologic\Core\GraphQL\ValidationRules;

use Shopologic\Core\GraphQL\ValidationContext;
use Shopologic\Core\GraphQL\ValidationRule;
use Shopologic\Core\GraphQL\Document;
use Shopologic\Core\GraphQL\OperationDefinition;
use Shopologic\Core\GraphQL\Field;
use Shopologic\Core\GraphQL\FragmentDefinition;
use Shopologic\Core\GraphQL\FragmentSpread;
use Shopologic\Core\GraphQL\SelectionSet;
use Shopologic\Core\GraphQL\Variable;
use Shopologic\Core\GraphQL\Argument;
use Shopologic\Core\GraphQL\ObjectType;
use Shopologic\Core\GraphQL\InterfaceType;
use Shopologic\Core\GraphQL\UnionType;

/**
 * Ensure fields exist on the type
 */
class FieldsOnCorrectType extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        $document = $context->getDocument();
        
        foreach ($document->getOperations() as $operation) {
            $type = $this->getOperationType($context, $operation);
            if ($type) {
                $errors = array_merge($errors, $this->validateSelectionSet(
                    $context,
                    $operation->selectionSet,
                    $type
                ));
            }
        }
        
        foreach ($document->getFragments() as $fragment) {
            $type = $context->getType($fragment->typeCondition);
            if ($type) {
                $errors = array_merge($errors, $this->validateSelectionSet(
                    $context,
                    $fragment->selectionSet,
                    $type
                ));
            }
        }
        
        return $errors;
    }
    
    private function validateSelectionSet(
        ValidationContext $context,
        SelectionSet $selectionSet,
        $type
    ): array {
        $errors = [];
        
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof Field) {
                if (!$this->isValidField($type, $selection->name)) {
                    $errors = array_merge($errors, $context->reportError(
                        "Cannot query field '{$selection->name}' on type '{$type->getName()}'",
                        $selection
                    ));
                } else {
                    // Validate nested selection set
                    if ($selection->selectionSet) {
                        $fieldType = $this->getFieldType($type, $selection->name);
                        if ($fieldType) {
                            $errors = array_merge($errors, $this->validateSelectionSet(
                                $context,
                                $selection->selectionSet,
                                $fieldType
                            ));
                        }
                    }
                }
            }
        }
        
        return $errors;
    }
    
    private function getOperationType(ValidationContext $context, OperationDefinition $operation)
    {
        $schema = $context->getSchema();
        
        switch ($operation->type) {
            case 'query':
                return $schema->getQueryType();
            case 'mutation':
                return $schema->getMutationType();
            case 'subscription':
                return $schema->getSubscriptionType();
            default:
                return null;
        }
    }
    
    private function isValidField($type, string $fieldName): bool
    {
        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
            return $type->getField($fieldName) !== null;
        }
        
        return false;
    }
    
    private function getFieldType($type, string $fieldName)
    {
        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
            $field = $type->getField($fieldName);
            return $field ? $this->unwrapType($field->getType()) : null;
        }
        
        return null;
    }
    
    private function unwrapType($type)
    {
        while ($type->isNonNull() || $type->isList()) {
            $type = $type->getOfType();
        }
        
        return $type;
    }
}

/**
 * Ensure fragments are used on composite types
 */
class FragmentsOnCompositeTypes extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        
        foreach ($context->getFragments() as $fragment) {
            $type = $context->getType($fragment->typeCondition);
            
            if (!$type) {
                $errors = array_merge($errors, $context->reportError(
                    "Unknown type '{$fragment->typeCondition}'",
                    $fragment
                ));
            } elseif (!$this->isCompositeType($type)) {
                $errors = array_merge($errors, $context->reportError(
                    "Fragment cannot condition on non composite type '{$fragment->typeCondition}'",
                    $fragment
                ));
            }
        }
        
        return $errors;
    }
    
    private function isCompositeType($type): bool
    {
        return $type->isObject() || $type->isInterface() || $type->isUnion();
    }
}

/**
 * Ensure known argument names
 */
class KnownArgumentNames extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        $document = $context->getDocument();
        
        foreach ($document->getOperations() as $operation) {
            $errors = array_merge($errors, $this->validateSelectionSet(
                $context,
                $operation->selectionSet,
                $this->getOperationType($context, $operation)
            ));
        }
        
        return $errors;
    }
    
    private function validateSelectionSet(
        ValidationContext $context,
        SelectionSet $selectionSet,
        $type
    ): array {
        $errors = [];
        
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof Field) {
                $field = $type ? $type->getField($selection->name) : null;
                
                if ($field) {
                    $knownArgs = [];
                    foreach ($field->getArgs() as $arg) {
                        $knownArgs[$arg->getName()] = true;
                    }
                    
                    foreach ($selection->arguments as $arg) {
                        if (!isset($knownArgs[$arg->name])) {
                            $errors = array_merge($errors, $context->reportError(
                                "Unknown argument '{$arg->name}' on field '{$selection->name}'",
                                $arg
                            ));
                        }
                    }
                }
            }
        }
        
        return $errors;
    }
    
    private function getOperationType(ValidationContext $context, OperationDefinition $operation)
    {
        $schema = $context->getSchema();
        
        switch ($operation->type) {
            case 'query':
                return $schema->getQueryType();
            case 'mutation':
                return $schema->getMutationType();
            case 'subscription':
                return $schema->getSubscriptionType();
            default:
                return null;
        }
    }
}

/**
 * Ensure directives are known
 */
class KnownDirectives extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        $schema = $context->getSchema();
        
        // Validate directive usage throughout the document
        // This would need to traverse the entire AST
        
        return $errors;
    }
}

/**
 * Ensure fragment names are known
 */
class KnownFragmentNames extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        
        // Find all fragment spreads and validate they reference known fragments
        
        return $errors;
    }
}

/**
 * Ensure type names are known
 */
class KnownTypeNames extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        
        // Validate all type references in the document
        
        return $errors;
    }
}

/**
 * Ensure only one anonymous operation
 */
class LoneAnonymousOperation extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $operations = $context->getDocument()->getOperations();
        $anonymousCount = 0;
        
        foreach ($operations as $operation) {
            if (!$operation->name) {
                $anonymousCount++;
            }
        }
        
        if ($anonymousCount > 0 && count($operations) > 1) {
            return $context->reportError(
                'This anonymous operation must be the only defined operation'
            );
        }
        
        return [];
    }
}

/**
 * Ensure no fragment cycles
 */
class NoFragmentCycles extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        $visited = [];
        $spreads = [];
        
        // Build fragment spread dependencies
        foreach ($context->getFragments() as $fragment) {
            $spreads[$fragment->name] = $this->getFragmentSpreads($fragment->selectionSet);
        }
        
        // Check for cycles
        foreach ($context->getFragments() as $fragment) {
            $errors = array_merge($errors, $this->detectCycle(
                $context,
                $fragment->name,
                $spreads,
                [],
                $visited
            ));
        }
        
        return $errors;
    }
    
    private function getFragmentSpreads(SelectionSet $selectionSet): array
    {
        $spreads = [];
        
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FragmentSpread) {
                $spreads[] = $selection->name;
            } elseif ($selection instanceof Field && $selection->selectionSet) {
                $spreads = array_merge($spreads, $this->getFragmentSpreads($selection->selectionSet));
            }
        }
        
        return $spreads;
    }
    
    private function detectCycle(
        ValidationContext $context,
        string $fragmentName,
        array $spreads,
        array $path,
        array &$visited
    ): array {
        if (isset($visited[$fragmentName])) {
            return [];
        }
        
        if (in_array($fragmentName, $path)) {
            return $context->reportError(
                "Cannot spread fragment '{$fragmentName}' within itself via " . implode(', ', $path)
            );
        }
        
        $visited[$fragmentName] = true;
        $errors = [];
        
        if (isset($spreads[$fragmentName])) {
            foreach ($spreads[$fragmentName] as $spread) {
                $errors = array_merge($errors, $this->detectCycle(
                    $context,
                    $spread,
                    $spreads,
                    array_merge($path, [$fragmentName]),
                    $visited
                ));
            }
        }
        
        return $errors;
    }
}

/**
 * Ensure no undefined variables
 */
class NoUndefinedVariables extends ValidationRule
{
    public function validate(ValidationContext $context): array
    {
        $errors = [];
        
        foreach ($context->getDocument()->getOperations() as $operation) {
            $definedVars = [];
            
            foreach ($operation->variableDefinitions as $varDef) {
                $definedVars[$varDef->name] = true;
            }
            
            $usedVars = $this->getUsedVariables($operation->selectionSet);
            
            foreach ($usedVars as $varName) {
                if (!isset($definedVars[$varName])) {
                    $errors = array_merge($errors, $context->reportError(
                        "Variable '\${$varName}' is not defined"
                    ));
                }
            }
        }
        
        return $errors;
    }
    
    private function getUsedVariables(SelectionSet $selectionSet): array
    {
        $vars = [];
        
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof Field) {
                foreach ($selection->arguments as $arg) {
                    $vars = array_merge($vars, $this->getVariablesFromValue($arg->value));
                }
                
                if ($selection->selectionSet) {
                    $vars = array_merge($vars, $this->getUsedVariables($selection->selectionSet));
                }
            }
        }
        
        return array_unique($vars);
    }
    
    private function getVariablesFromValue($value): array
    {
        if ($value instanceof Variable) {
            return [$value->name];
        }
        
        // Handle other value types (lists, objects, etc.)
        
        return [];
    }
}

/**
 * Additional validation rules would be implemented similarly:
 * - NoUnusedFragments
 * - NoUnusedVariables
 * - OverlappingFieldsCanBeMerged
 * - PossibleFragmentSpreads
 * - ProvidedRequiredArguments
 * - ScalarLeafs
 * - SingleFieldSubscriptions
 * - UniqueArgumentNames
 * - UniqueDirectivesPerLocation
 * - UniqueFragmentNames
 * - UniqueInputFieldNames
 * - UniqueOperationNames
 * - UniqueVariableNames
 * - ValuesOfCorrectType
 * - VariablesAreInputTypes
 * - VariablesInAllowedPosition
 * - QueryDepth
 * - DisableIntrospection
 */

// Placeholder implementations for remaining rules
class NoUnusedFragments extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class NoUnusedVariables extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class OverlappingFieldsCanBeMerged extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class PossibleFragmentSpreads extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class ProvidedRequiredArguments extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class ScalarLeafs extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class SingleFieldSubscriptions extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class UniqueArgumentNames extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class UniqueDirectivesPerLocation extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class UniqueFragmentNames extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class UniqueInputFieldNames extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class UniqueOperationNames extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class UniqueVariableNames extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class ValuesOfCorrectType extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class VariablesAreInputTypes extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }
class VariablesInAllowedPosition extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }

class QueryDepth extends ValidationRule 
{
    private int $maxDepth;
    
    public function __construct(int $maxDepth)
    {
        $this->maxDepth = $maxDepth;
    }
    
    public function validate(ValidationContext $context): array { return []; } 
}

class DisableIntrospection extends ValidationRule { public function validate(ValidationContext $context): array { return []; } }