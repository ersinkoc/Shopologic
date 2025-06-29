<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

/**
 * GraphQL schema definition
 */
class Schema
{
    private Type $queryType;
    private ?Type $mutationType = null;
    private ?Type $subscriptionType = null;
    private array $types = [];
    private array $directives = [];
    
    public function __construct(array $config)
    {
        $this->queryType = $config['query'];
        $this->mutationType = $config['mutation'] ?? null;
        $this->subscriptionType = $config['subscription'] ?? null;
        $this->types = $config['types'] ?? [];
        $this->directives = array_merge($this->getDefaultDirectives(), $config['directives'] ?? []);
        
        // Register root types
        $this->addType($this->queryType);
        
        if ($this->mutationType) {
            $this->addType($this->mutationType);
        }
        
        if ($this->subscriptionType) {
            $this->addType($this->subscriptionType);
        }
        
        // Register additional types
        foreach ($this->types as $type) {
            $this->addType($type);
        }
    }
    
    public function getQueryType(): Type
    {
        return $this->queryType;
    }
    
    public function getMutationType(): ?Type
    {
        return $this->mutationType;
    }
    
    public function getSubscriptionType(): ?Type
    {
        return $this->subscriptionType;
    }
    
    public function getType(string $name): ?Type
    {
        // Check standard types
        $standardType = $this->getStandardType($name);
        if ($standardType) {
            return $standardType;
        }
        
        // Check registered types
        foreach ($this->types as $type) {
            if ($type->getName() === $name) {
                return $type;
            }
        }
        
        return null;
    }
    
    public function getTypes(): array
    {
        return $this->types;
    }
    
    public function getDirective(string $name): ?Directive
    {
        return $this->directives[$name] ?? null;
    }
    
    public function getDirectives(): array
    {
        return array_values($this->directives);
    }
    
    public function validate(): array
    {
        $errors = [];
        
        // Validate query type
        if (!$this->queryType) {
            $errors[] = 'Schema must have a query type';
        }
        
        // Validate all types
        foreach ($this->types as $type) {
            $errors = array_merge($errors, $type->validate());
        }
        
        return $errors;
    }
    
    private function addType(Type $type): void
    {
        $this->types[] = $type;
        
        // Recursively add field types
        if ($type instanceof ObjectType) {
            foreach ($type->getFields() as $field) {
                $fieldType = $field->getType();
                
                if ($fieldType instanceof Type && !$this->hasType($fieldType)) {
                    $this->addType($fieldType);
                }
            }
        }
    }
    
    private function hasType(Type $type): bool
    {
        foreach ($this->types as $existingType) {
            if ($existingType->getName() === $type->getName()) {
                return true;
            }
        }
        
        return false;
    }
    
    private function getStandardType(string $name): ?Type
    {
        return match($name) {
            'String' => ScalarType::string(),
            'Int' => ScalarType::int(),
            'Float' => ScalarType::float(),
            'Boolean' => ScalarType::boolean(),
            'ID' => ScalarType::id(),
            default => null
        };
    }
    
    private function getDefaultDirectives(): array
    {
        return [
            'skip' => new Directive([
                'name' => 'skip',
                'description' => 'Directs the executor to skip this field or fragment when the `if` argument is true.',
                'locations' => ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT'],
                'args' => [
                    'if' => [
                        'type' => Type::nonNull(ScalarType::boolean()),
                        'description' => 'Skipped when true.'
                    ]
                ]
            ]),
            'include' => new Directive([
                'name' => 'include',
                'description' => 'Directs the executor to include this field or fragment only when the `if` argument is true.',
                'locations' => ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT'],
                'args' => [
                    'if' => [
                        'type' => Type::nonNull(ScalarType::boolean()),
                        'description' => 'Included when true.'
                    ]
                ]
            ]),
            'deprecated' => new Directive([
                'name' => 'deprecated',
                'description' => 'Marks an element of a GraphQL schema as no longer supported.',
                'locations' => ['FIELD_DEFINITION', 'ENUM_VALUE'],
                'args' => [
                    'reason' => [
                        'type' => ScalarType::string(),
                        'description' => 'Explains why this element was deprecated.',
                        'defaultValue' => 'No longer supported'
                    ]
                ]
            ])
        ];
    }
}

/**
 * Base type class
 */
abstract class Type
{
    protected string $name;
    protected ?string $description;
    
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->description = $config['description'] ?? null;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function isNonNull(): bool
    {
        return false;
    }
    
    public function isList(): bool
    {
        return false;
    }
    
    public function isScalar(): bool
    {
        return false;
    }
    
    public function isObject(): bool
    {
        return false;
    }
    
    public function isInterface(): bool
    {
        return false;
    }
    
    public function isUnion(): bool
    {
        return false;
    }
    
    public function isEnum(): bool
    {
        return false;
    }
    
    public function isInputObject(): bool
    {
        return false;
    }
    
    public static function nonNull(Type $type): NonNullType
    {
        return new NonNullType($type);
    }
    
    public static function list(Type $type): ListType
    {
        return new ListType($type);
    }
    
    abstract public function validate(): array;
}

/**
 * Object type
 */
class ObjectType extends Type
{
    protected array $fields = [];
    protected array $interfaces = [];
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        foreach ($config['fields'] as $name => $field) {
            $this->fields[$name] = new Field(array_merge($field, ['name' => $name]));
        }
        
        $this->interfaces = $config['interfaces'] ?? [];
    }
    
    public function getField(string $name): ?Field
    {
        return $this->fields[$name] ?? null;
    }
    
    public function getFields(): array
    {
        return $this->fields;
    }
    
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }
    
    public function isObject(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->fields)) {
            $errors[] = "Object type {$this->name} must have at least one field";
        }
        
        foreach ($this->fields as $field) {
            $errors = array_merge($errors, $field->validate());
        }
        
        return $errors;
    }
}

/**
 * Field definition
 */
class Field
{
    private string $name;
    private Type $type;
    private ?string $description;
    private array $args;
    private $resolver;
    private ?string $deprecationReason;
    
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->type = $config['type'];
        $this->description = $config['description'] ?? null;
        $this->args = [];
        $this->resolver = $config['resolve'] ?? null;
        $this->deprecationReason = $config['deprecationReason'] ?? null;
        
        foreach ($config['args'] ?? [] as $name => $arg) {
            $this->args[$name] = new Argument(array_merge($arg, ['name' => $name]));
        }
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getType(): Type
    {
        return $this->type;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getArgs(): array
    {
        return $this->args;
    }
    
    public function getResolver()
    {
        return $this->resolver;
    }
    
    public function isDeprecated(): bool
    {
        return $this->deprecationReason !== null;
    }
    
    public function getDeprecationReason(): ?string
    {
        return $this->deprecationReason;
    }
    
    public function validate(): array
    {
        $errors = [];
        
        foreach ($this->args as $arg) {
            $errors = array_merge($errors, $arg->validate());
        }
        
        return $errors;
    }
}

/**
 * Argument definition
 */
class Argument
{
    private string $name;
    private Type $type;
    private ?string $description;
    private $defaultValue;
    
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->type = $config['type'];
        $this->description = $config['description'] ?? null;
        $this->defaultValue = $config['defaultValue'] ?? null;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getType(): Type
    {
        return $this->type;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
    
    public function hasDefaultValue(): bool
    {
        return $this->defaultValue !== null;
    }
    
    public function validate(): array
    {
        return [];
    }
}

/**
 * Scalar type
 */
class ScalarType extends Type
{
    private $serialize;
    private $parseValue;
    private $parseLiteral;
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        $this->serialize = $config['serialize'];
        $this->parseValue = $config['parseValue'];
        $this->parseLiteral = $config['parseLiteral'];
    }
    
    public function serialize($value)
    {
        return ($this->serialize)($value);
    }
    
    public function parseValue($value)
    {
        return ($this->parseValue)($value);
    }
    
    public function parseLiteral($ast)
    {
        return ($this->parseLiteral)($ast);
    }
    
    public function isScalar(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        return [];
    }
    
    public static function string(): self
    {
        return new self([
            'name' => 'String',
            'description' => 'The `String` scalar type represents textual data.',
            'serialize' => fn($value) => (string)$value,
            'parseValue' => fn($value) => is_string($value) ? $value : null,
            'parseLiteral' => fn($ast) => $ast->kind === 'StringValue' ? $ast->value : null
        ]);
    }
    
    public static function int(): self
    {
        return new self([
            'name' => 'Int',
            'description' => 'The `Int` scalar type represents non-fractional signed whole numeric values.',
            'serialize' => fn($value) => (int)$value,
            'parseValue' => fn($value) => is_int($value) ? $value : null,
            'parseLiteral' => fn($ast) => $ast->kind === 'IntValue' ? (int)$ast->value : null
        ]);
    }
    
    public static function float(): self
    {
        return new self([
            'name' => 'Float',
            'description' => 'The `Float` scalar type represents signed double-precision fractional values.',
            'serialize' => fn($value) => (float)$value,
            'parseValue' => fn($value) => is_float($value) || is_int($value) ? (float)$value : null,
            'parseLiteral' => fn($ast) => in_array($ast->kind, ['IntValue', 'FloatValue']) ? (float)$ast->value : null
        ]);
    }
    
    public static function boolean(): self
    {
        return new self([
            'name' => 'Boolean',
            'description' => 'The `Boolean` scalar type represents `true` or `false`.',
            'serialize' => fn($value) => (bool)$value,
            'parseValue' => fn($value) => is_bool($value) ? $value : null,
            'parseLiteral' => fn($ast) => $ast->kind === 'BooleanValue' ? $ast->value : null
        ]);
    }
    
    public static function id(): self
    {
        return new self([
            'name' => 'ID',
            'description' => 'The `ID` scalar type represents a unique identifier.',
            'serialize' => fn($value) => (string)$value,
            'parseValue' => fn($value) => is_string($value) || is_int($value) ? (string)$value : null,
            'parseLiteral' => fn($ast) => in_array($ast->kind, ['StringValue', 'IntValue']) ? $ast->value : null
        ]);
    }
}

/**
 * List type wrapper
 */
class ListType extends Type
{
    private Type $ofType;
    
    public function __construct(Type $ofType)
    {
        $this->ofType = $ofType;
        $this->name = "[{$ofType->getName()}]";
    }
    
    public function getOfType(): Type
    {
        return $this->ofType;
    }
    
    public function isList(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        return $this->ofType->validate();
    }
}

/**
 * Non-null type wrapper
 */
class NonNullType extends Type
{
    private Type $ofType;
    
    public function __construct(Type $ofType)
    {
        if ($ofType->isNonNull()) {
            throw new \InvalidArgumentException('Cannot wrap NonNullType in NonNullType');
        }
        
        $this->ofType = $ofType;
        $this->name = "{$ofType->getName()}!";
    }
    
    public function getOfType(): Type
    {
        return $this->ofType;
    }
    
    public function isNonNull(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        return $this->ofType->validate();
    }
}

/**
 * Interface type
 */
class InterfaceType extends Type
{
    protected array $fields = [];
    protected $resolveType;
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        foreach ($config['fields'] as $name => $field) {
            $this->fields[$name] = new Field(array_merge($field, ['name' => $name]));
        }
        
        $this->resolveType = $config['resolveType'] ?? null;
    }
    
    public function getFields(): array
    {
        return $this->fields;
    }
    
    public function getResolveType()
    {
        return $this->resolveType;
    }
    
    public function isInterface(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->fields)) {
            $errors[] = "Interface type {$this->name} must have at least one field";
        }
        
        foreach ($this->fields as $field) {
            $errors = array_merge($errors, $field->validate());
        }
        
        return $errors;
    }
}

/**
 * Union type
 */
class UnionType extends Type
{
    private array $types;
    private $resolveType;
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        $this->types = $config['types'];
        $this->resolveType = $config['resolveType'] ?? null;
    }
    
    public function getTypes(): array
    {
        return $this->types;
    }
    
    public function getResolveType()
    {
        return $this->resolveType;
    }
    
    public function isUnion(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        $errors = [];
        
        if (count($this->types) < 2) {
            $errors[] = "Union type {$this->name} must have at least two possible types";
        }
        
        return $errors;
    }
}

/**
 * Enum type
 */
class EnumType extends Type
{
    private array $values;
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        $this->values = [];
        foreach ($config['values'] as $name => $value) {
            $this->values[$name] = new EnumValue(array_merge($value, ['name' => $name]));
        }
    }
    
    public function getValues(): array
    {
        return $this->values;
    }
    
    public function getValue(string $name): ?EnumValue
    {
        return $this->values[$name] ?? null;
    }
    
    public function isEnum(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->values)) {
            $errors[] = "Enum type {$this->name} must have at least one value";
        }
        
        return $errors;
    }
}

/**
 * Enum value
 */
class EnumValue
{
    private string $name;
    private $value;
    private ?string $description;
    private ?string $deprecationReason;
    
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->value = $config['value'] ?? $config['name'];
        $this->description = $config['description'] ?? null;
        $this->deprecationReason = $config['deprecationReason'] ?? null;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getValue()
    {
        return $this->value;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function isDeprecated(): bool
    {
        return $this->deprecationReason !== null;
    }
    
    public function getDeprecationReason(): ?string
    {
        return $this->deprecationReason;
    }
}

/**
 * Input object type
 */
class InputObjectType extends Type
{
    private array $fields;
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        $this->fields = [];
        foreach ($config['fields'] as $name => $field) {
            $this->fields[$name] = new InputField(array_merge($field, ['name' => $name]));
        }
    }
    
    public function getFields(): array
    {
        return $this->fields;
    }
    
    public function getField(string $name): ?InputField
    {
        return $this->fields[$name] ?? null;
    }
    
    public function isInputObject(): bool
    {
        return true;
    }
    
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->fields)) {
            $errors[] = "Input object type {$this->name} must have at least one field";
        }
        
        return $errors;
    }
}

/**
 * Input field
 */
class InputField
{
    private string $name;
    private Type $type;
    private ?string $description;
    private $defaultValue;
    
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->type = $config['type'];
        $this->description = $config['description'] ?? null;
        $this->defaultValue = $config['defaultValue'] ?? null;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getType(): Type
    {
        return $this->type;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
    
    public function hasDefaultValue(): bool
    {
        return $this->defaultValue !== null;
    }
}

/**
 * Directive definition
 */
class Directive
{
    private string $name;
    private ?string $description;
    private array $locations;
    private array $args;
    
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->description = $config['description'] ?? null;
        $this->locations = $config['locations'];
        $this->args = [];
        
        foreach ($config['args'] ?? [] as $name => $arg) {
            $this->args[$name] = new Argument(array_merge($arg, ['name' => $name]));
        }
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getLocations(): array
    {
        return $this->locations;
    }
    
    public function getArgs(): array
    {
        return $this->args;
    }
}