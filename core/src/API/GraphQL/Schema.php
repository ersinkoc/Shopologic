<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\GraphQL;

class Schema
{
    protected array $types = [];
    protected array $queries = [];
    protected array $mutations = [];
    protected array $subscriptions = [];
    protected array $directives = [];
    protected array $scalars = [];

    /**
     * Add a type to the schema
     */
    public function type(string $name, array $definition): self
    {
        $this->types[$name] = new Type($name, $definition);
        return $this;
    }

    /**
     * Add a query field
     */
    public function query(string $name, array $definition): self
    {
        $this->queries[$name] = $definition;
        return $this;
    }

    /**
     * Add a mutation field
     */
    public function mutation(string $name, array $definition): self
    {
        $this->mutations[$name] = $definition;
        return $this;
    }

    /**
     * Add a subscription field
     */
    public function subscription(string $name, array $definition): self
    {
        $this->subscriptions[$name] = $definition;
        return $this;
    }

    /**
     * Add a custom scalar
     */
    public function scalar(string $name, array $definition): self
    {
        $this->scalars[$name] = new Scalar($name, $definition);
        return $this;
    }

    /**
     * Add a directive
     */
    public function directive(string $name, array $definition): self
    {
        $this->directives[$name] = new Directive($name, $definition);
        return $this;
    }

    /**
     * Build the schema
     */
    public function build(): array
    {
        $schema = [
            'types' => $this->buildTypes(),
        ];

        if (!empty($this->queries)) {
            $schema['query'] = $this->buildRootType('Query', $this->queries);
        }

        if (!empty($this->mutations)) {
            $schema['mutation'] = $this->buildRootType('Mutation', $this->mutations);
        }

        if (!empty($this->subscriptions)) {
            $schema['subscription'] = $this->buildRootType('Subscription', $this->subscriptions);
        }

        if (!empty($this->directives)) {
            $schema['directives'] = $this->directives;
        }

        return $schema;
    }

    /**
     * Build types
     */
    protected function buildTypes(): array
    {
        $types = array_merge($this->getDefaultTypes(), $this->types, $this->scalars);
        
        $built = [];
        foreach ($types as $name => $type) {
            if ($type instanceof Type || $type instanceof Scalar) {
                $built[$name] = $type->toArray();
            } else {
                $built[$name] = $type;
            }
        }
        
        return $built;
    }

    /**
     * Build root type
     */
    protected function buildRootType(string $name, array $fields): array
    {
        return [
            'name' => $name,
            'fields' => $fields,
        ];
    }

    /**
     * Get default scalar types
     */
    protected function getDefaultTypes(): array
    {
        return [
            'Int' => ['type' => 'scalar', 'description' => 'Integer'],
            'Float' => ['type' => 'scalar', 'description' => 'Float'],
            'String' => ['type' => 'scalar', 'description' => 'String'],
            'Boolean' => ['type' => 'scalar', 'description' => 'Boolean'],
            'ID' => ['type' => 'scalar', 'description' => 'ID'],
        ];
    }

    /**
     * Generate SDL (Schema Definition Language)
     */
    public function toSDL(): string
    {
        $sdl = [];

        // Add scalar types
        foreach ($this->scalars as $name => $scalar) {
            $sdl[] = "scalar $name";
        }

        // Add object types
        foreach ($this->types as $name => $type) {
            $sdl[] = $type->toSDL();
        }

        // Add Query type
        if (!empty($this->queries)) {
            $sdl[] = $this->rootTypeToSDL('Query', $this->queries);
        }

        // Add Mutation type
        if (!empty($this->mutations)) {
            $sdl[] = $this->rootTypeToSDL('Mutation', $this->mutations);
        }

        // Add Subscription type
        if (!empty($this->subscriptions)) {
            $sdl[] = $this->rootTypeToSDL('Subscription', $this->subscriptions);
        }

        // Add schema definition
        $schemaDef = ['schema {'];
        if (!empty($this->queries)) {
            $schemaDef[] = '  query: Query';
        }
        if (!empty($this->mutations)) {
            $schemaDef[] = '  mutation: Mutation';
        }
        if (!empty($this->subscriptions)) {
            $schemaDef[] = '  subscription: Subscription';
        }
        $schemaDef[] = '}';
        
        $sdl[] = implode("\n", $schemaDef);

        return implode("\n\n", $sdl);
    }

    /**
     * Convert root type to SDL
     */
    protected function rootTypeToSDL(string $typeName, array $fields): string
    {
        $lines = ["type $typeName {"];
        
        foreach ($fields as $name => $field) {
            $args = '';
            if (!empty($field['args'])) {
                $argDefs = [];
                foreach ($field['args'] as $argName => $argType) {
                    $argDefs[] = "$argName: $argType";
                }
                $args = '(' . implode(', ', $argDefs) . ')';
            }
            
            $type = $field['type'] ?? 'String';
            $lines[] = "  $name$args: $type";
        }
        
        $lines[] = '}';
        
        return implode("\n", $lines);
    }
}