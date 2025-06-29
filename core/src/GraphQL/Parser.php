<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

/**
 * GraphQL parser for building AST from tokens
 */
class Parser
{
    private Lexer $lexer;
    private ?Token $currentToken = null;
    
    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
        $this->advance();
    }
    
    /**
     * Parse GraphQL document
     */
    public function parse(): Document
    {
        $definitions = [];
        
        while ($this->currentToken && $this->currentToken->type !== 'EOF') {
            $definitions[] = $this->parseDefinition();
        }
        
        return new Document($definitions);
    }
    
    /**
     * Parse definition
     */
    private function parseDefinition(): Definition
    {
        // Check for fragment
        if ($this->match('FRAGMENT')) {
            return $this->parseFragmentDefinition();
        }
        
        // Check for named operation
        if ($this->match('QUERY') || $this->match('MUTATION') || $this->match('SUBSCRIPTION')) {
            return $this->parseOperationDefinition();
        }
        
        // Check for anonymous operation
        if ($this->match('{')) {
            return $this->parseOperationDefinition();
        }
        
        // Check for type system definitions
        if ($this->match('TYPE') || $this->match('INTERFACE') || $this->match('UNION') || 
            $this->match('ENUM') || $this->match('INPUT') || $this->match('SCALAR') || 
            $this->match('DIRECTIVE') || $this->match('SCHEMA') || $this->match('EXTEND')) {
            return $this->parseTypeSystemDefinition();
        }
        
        throw new \Exception("Expected definition at line {$this->currentToken->line}");
    }
    
    /**
     * Parse operation definition
     */
    private function parseOperationDefinition(): OperationDefinition
    {
        $type = 'query';
        $name = null;
        $variableDefinitions = [];
        $directives = [];
        
        // Parse operation type and name
        if (!$this->match('{')) {
            if ($this->match('QUERY')) {
                $type = 'query';
                $this->advance();
            } elseif ($this->match('MUTATION')) {
                $type = 'mutation';
                $this->advance();
            } elseif ($this->match('SUBSCRIPTION')) {
                $type = 'subscription';
                $this->advance();
            }
            
            // Optional operation name
            if ($this->match('NAME')) {
                $name = $this->currentToken->value;
                $this->advance();
            }
            
            // Optional variable definitions
            if ($this->match('(')) {
                $variableDefinitions = $this->parseVariableDefinitions();
            }
            
            // Optional directives
            $directives = $this->parseDirectives();
        }
        
        // Selection set
        $selectionSet = $this->parseSelectionSet();
        
        return new OperationDefinition($type, $selectionSet, $name, $variableDefinitions, $directives);
    }
    
    /**
     * Parse fragment definition
     */
    private function parseFragmentDefinition(): FragmentDefinition
    {
        $this->expect('FRAGMENT');
        
        $name = $this->expectName();
        
        $this->expect('ON');
        
        $typeCondition = $this->expectName();
        
        $directives = $this->parseDirectives();
        
        $selectionSet = $this->parseSelectionSet();
        
        return new FragmentDefinition($name, $typeCondition, $selectionSet, $directives);
    }
    
    /**
     * Parse selection set
     */
    private function parseSelectionSet(): SelectionSet
    {
        $this->expect('{');
        
        $selections = [];
        
        while (!$this->match('}') && $this->currentToken) {
            $selections[] = $this->parseSelection();
        }
        
        $this->expect('}');
        
        return new SelectionSet($selections);
    }
    
    /**
     * Parse selection
     */
    private function parseSelection(): Selection
    {
        if ($this->match('SPREAD')) {
            return $this->parseFragmentSpread();
        }
        
        return $this->parseField();
    }
    
    /**
     * Parse field
     */
    private function parseField(): Field
    {
        $alias = null;
        $name = $this->expectName();
        
        // Check for alias
        if ($this->match(':')) {
            $this->advance();
            $alias = $name;
            $name = $this->expectName();
        }
        
        $arguments = [];
        if ($this->match('(')) {
            $arguments = $this->parseArguments();
        }
        
        $directives = $this->parseDirectives();
        
        $selectionSet = null;
        if ($this->match('{')) {
            $selectionSet = $this->parseSelectionSet();
        }
        
        return new Field($name, $alias, $arguments, $directives, $selectionSet);
    }
    
    /**
     * Parse fragment spread
     */
    private function parseFragmentSpread(): FragmentSpread
    {
        $this->expect('SPREAD');
        
        // Check for inline fragment
        if ($this->match('ON')) {
            $this->advance();
            $typeCondition = $this->expectName();
            $directives = $this->parseDirectives();
            $selectionSet = $this->parseSelectionSet();
            
            return new InlineFragment($typeCondition, $selectionSet, $directives);
        }
        
        // Named fragment spread
        $name = $this->expectName();
        $directives = $this->parseDirectives();
        
        return new FragmentSpread($name, $directives);
    }
    
    /**
     * Parse arguments
     */
    private function parseArguments(): array
    {
        $this->expect('(');
        
        $arguments = [];
        
        while (!$this->match(')') && $this->currentToken) {
            $name = $this->expectName();
            $this->expect(':');
            $value = $this->parseValue();
            
            $arguments[] = new Argument($name, $value);
            
            // Optional comma
            if ($this->match(',')) {
                $this->advance();
            }
        }
        
        $this->expect(')');
        
        return $arguments;
    }
    
    /**
     * Parse value
     */
    private function parseValue(): Value
    {
        if ($this->match('$')) {
            return $this->parseVariable();
        }
        
        if ($this->match('INT')) {
            $value = new IntValue((int)$this->currentToken->value);
            $this->advance();
            return $value;
        }
        
        if ($this->match('FLOAT')) {
            $value = new FloatValue((float)$this->currentToken->value);
            $this->advance();
            return $value;
        }
        
        if ($this->match('STRING')) {
            $value = new StringValue($this->currentToken->value);
            $this->advance();
            return $value;
        }
        
        if ($this->match('BOOLEAN')) {
            $value = new BooleanValue($this->currentToken->value === 'true');
            $this->advance();
            return $value;
        }
        
        if ($this->match('NULL')) {
            $this->advance();
            return new NullValue();
        }
        
        if ($this->match('[')) {
            return $this->parseListValue();
        }
        
        if ($this->match('{')) {
            return $this->parseObjectValue();
        }
        
        if ($this->match('NAME')) {
            $value = new EnumValue($this->currentToken->value);
            $this->advance();
            return $value;
        }
        
        throw new \Exception("Expected value at line {$this->currentToken->line}");
    }
    
    /**
     * Parse variable
     */
    private function parseVariable(): Variable
    {
        $this->expect('$');
        $name = $this->expectName();
        
        return new Variable($name);
    }
    
    /**
     * Parse list value
     */
    private function parseListValue(): ListValue
    {
        $this->expect('[');
        
        $values = [];
        
        while (!$this->match(']') && $this->currentToken) {
            $values[] = $this->parseValue();
            
            // Optional comma
            if ($this->match(',')) {
                $this->advance();
            }
        }
        
        $this->expect(']');
        
        return new ListValue($values);
    }
    
    /**
     * Parse object value
     */
    private function parseObjectValue(): ObjectValue
    {
        $this->expect('{');
        
        $fields = [];
        
        while (!$this->match('}') && $this->currentToken) {
            $name = $this->expectName();
            $this->expect(':');
            $value = $this->parseValue();
            
            $fields[] = new ObjectField($name, $value);
            
            // Optional comma
            if ($this->match(',')) {
                $this->advance();
            }
        }
        
        $this->expect('}');
        
        return new ObjectValue($fields);
    }
    
    /**
     * Parse variable definitions
     */
    private function parseVariableDefinitions(): array
    {
        $this->expect('(');
        
        $definitions = [];
        
        while (!$this->match(')') && $this->currentToken) {
            $definitions[] = $this->parseVariableDefinition();
            
            // Optional comma
            if ($this->match(',')) {
                $this->advance();
            }
        }
        
        $this->expect(')');
        
        return $definitions;
    }
    
    /**
     * Parse variable definition
     */
    private function parseVariableDefinition(): VariableDefinition
    {
        $this->expect('$');
        $name = $this->expectName();
        $this->expect(':');
        $type = $this->parseType();
        
        $defaultValue = null;
        if ($this->match('=')) {
            $this->advance();
            $defaultValue = $this->parseValue();
        }
        
        return new VariableDefinition($name, $type, $defaultValue);
    }
    
    /**
     * Parse type
     */
    private function parseType(): TypeNode
    {
        $type = null;
        
        if ($this->match('[')) {
            $this->advance();
            $type = new ListTypeNode($this->parseType());
            $this->expect(']');
        } else {
            $type = new NamedTypeNode($this->expectName());
        }
        
        if ($this->match('!')) {
            $this->advance();
            $type = new NonNullTypeNode($type);
        }
        
        return $type;
    }
    
    /**
     * Parse directives
     */
    private function parseDirectives(): array
    {
        $directives = [];
        
        while ($this->match('@') && $this->currentToken) {
            $this->advance();
            $name = $this->expectName();
            
            $arguments = [];
            if ($this->match('(')) {
                $arguments = $this->parseArguments();
            }
            
            $directives[] = new DirectiveNode($name, $arguments);
        }
        
        return $directives;
    }
    
    /**
     * Parse type system definition
     */
    private function parseTypeSystemDefinition(): TypeSystemDefinition
    {
        // Simplified - would need full implementation
        throw new \Exception('Type system definitions not yet implemented');
    }
    
    /**
     * Advance to next token
     */
    private function advance(): void
    {
        $this->currentToken = $this->lexer->nextToken();
    }
    
    /**
     * Check if current token matches type
     */
    private function match(string $type): bool
    {
        return $this->currentToken && $this->currentToken->type === $type;
    }
    
    /**
     * Expect token of specific type
     */
    private function expect(string $type): void
    {
        if (!$this->match($type)) {
            throw new \Exception(
                "Expected {$type} but got {$this->currentToken->type} at line {$this->currentToken->line}"
            );
        }
        $this->advance();
    }
    
    /**
     * Expect name token
     */
    private function expectName(): string
    {
        if (!$this->match('NAME')) {
            throw new \Exception(
                "Expected name but got {$this->currentToken->type} at line {$this->currentToken->line}"
            );
        }
        
        $name = $this->currentToken->value;
        $this->advance();
        
        return $name;
    }
}

// AST Node classes
abstract class Node {}

class Document extends Node
{
    public array $definitions;
    
    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
    }
    
    public function getOperations(): array
    {
        return array_filter($this->definitions, fn($def) => $def instanceof OperationDefinition);
    }
    
    public function getFragments(): array
    {
        return array_filter($this->definitions, fn($def) => $def instanceof FragmentDefinition);
    }
}

abstract class Definition extends Node {}

class OperationDefinition extends Definition
{
    public string $type;
    public SelectionSet $selectionSet;
    public ?string $name;
    public array $variableDefinitions;
    public array $directives;
    
    public function __construct(
        string $type,
        SelectionSet $selectionSet,
        ?string $name = null,
        array $variableDefinitions = [],
        array $directives = []
    ) {
        $this->type = $type;
        $this->selectionSet = $selectionSet;
        $this->name = $name;
        $this->variableDefinitions = $variableDefinitions;
        $this->directives = $directives;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
}

class FragmentDefinition extends Definition
{
    public string $name;
    public string $typeCondition;
    public SelectionSet $selectionSet;
    public array $directives;
    
    public function __construct(
        string $name,
        string $typeCondition,
        SelectionSet $selectionSet,
        array $directives = []
    ) {
        $this->name = $name;
        $this->typeCondition = $typeCondition;
        $this->selectionSet = $selectionSet;
        $this->directives = $directives;
    }
}

class SelectionSet extends Node
{
    public array $selections;
    
    public function __construct(array $selections)
    {
        $this->selections = $selections;
    }
}

abstract class Selection extends Node {}

class Field extends Selection
{
    public string $name;
    public ?string $alias;
    public array $arguments;
    public array $directives;
    public ?SelectionSet $selectionSet;
    
    public function __construct(
        string $name,
        ?string $alias = null,
        array $arguments = [],
        array $directives = [],
        ?SelectionSet $selectionSet = null
    ) {
        $this->name = $name;
        $this->alias = $alias;
        $this->arguments = $arguments;
        $this->directives = $directives;
        $this->selectionSet = $selectionSet;
    }
}

class FragmentSpread extends Selection
{
    public string $name;
    public array $directives;
    
    public function __construct(string $name, array $directives = [])
    {
        $this->name = $name;
        $this->directives = $directives;
    }
}

class InlineFragment extends Selection
{
    public ?string $typeCondition;
    public SelectionSet $selectionSet;
    public array $directives;
    
    public function __construct(
        ?string $typeCondition,
        SelectionSet $selectionSet,
        array $directives = []
    ) {
        $this->typeCondition = $typeCondition;
        $this->selectionSet = $selectionSet;
        $this->directives = $directives;
    }
}

class Argument extends Node
{
    public string $name;
    public Value $value;
    
    public function __construct(string $name, Value $value)
    {
        $this->name = $name;
        $this->value = $value;
    }
}

abstract class Value extends Node {}

class Variable extends Value
{
    public string $name;
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

class IntValue extends Value
{
    public int $value;
    
    public function __construct(int $value)
    {
        $this->value = $value;
    }
}

class FloatValue extends Value
{
    public float $value;
    
    public function __construct(float $value)
    {
        $this->value = $value;
    }
}

class StringValue extends Value
{
    public string $value;
    
    public function __construct(string $value)
    {
        $this->value = $value;
    }
}

class BooleanValue extends Value
{
    public bool $value;
    
    public function __construct(bool $value)
    {
        $this->value = $value;
    }
}

class NullValue extends Value {}

class EnumValue extends Value
{
    public string $value;
    
    public function __construct(string $value)
    {
        $this->value = $value;
    }
}

class ListValue extends Value
{
    public array $values;
    
    public function __construct(array $values)
    {
        $this->values = $values;
    }
}

class ObjectValue extends Value
{
    public array $fields;
    
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }
}

class ObjectField extends Node
{
    public string $name;
    public Value $value;
    
    public function __construct(string $name, Value $value)
    {
        $this->name = $name;
        $this->value = $value;
    }
}

class VariableDefinition extends Node
{
    public string $name;
    public TypeNode $type;
    public ?Value $defaultValue;
    
    public function __construct(string $name, TypeNode $type, ?Value $defaultValue = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->defaultValue = $defaultValue;
    }
}

abstract class TypeNode extends Node {}

class NamedTypeNode extends TypeNode
{
    public string $name;
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

class ListTypeNode extends TypeNode
{
    public TypeNode $type;
    
    public function __construct(TypeNode $type)
    {
        $this->type = $type;
    }
}

class NonNullTypeNode extends TypeNode
{
    public TypeNode $type;
    
    public function __construct(TypeNode $type)
    {
        $this->type = $type;
    }
}

class DirectiveNode extends Node
{
    public string $name;
    public array $arguments;
    
    public function __construct(string $name, array $arguments = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }
}

abstract class TypeSystemDefinition extends Definition {}