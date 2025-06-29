<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\GraphQL;

class Type
{
    protected string $name;
    protected array $fields = [];
    protected ?string $description = null;
    protected array $interfaces = [];

    public function __construct(string $name, array $definition)
    {
        $this->name = $name;
        $this->fields = $definition['fields'] ?? [];
        $this->description = $definition['description'] ?? null;
        $this->interfaces = $definition['interfaces'] ?? [];
    }

    /**
     * Get type name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add a field
     */
    public function field(string $name, array $definition): self
    {
        $this->fields[$name] = $definition;
        return $this;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'type' => 'object',
            'fields' => $this->fields,
        ];

        if ($this->description) {
            $array['description'] = $this->description;
        }

        if (!empty($this->interfaces)) {
            $array['interfaces'] = $this->interfaces;
        }

        return $array;
    }

    /**
     * Convert to SDL
     */
    public function toSDL(): string
    {
        $sdl = [];
        
        if ($this->description) {
            $sdl[] = '"""';
            $sdl[] = $this->description;
            $sdl[] = '"""';
        }
        
        $implements = '';
        if (!empty($this->interfaces)) {
            $implements = ' implements ' . implode(' & ', $this->interfaces);
        }
        
        $sdl[] = "type {$this->name}{$implements} {";
        
        foreach ($this->fields as $fieldName => $field) {
            $type = $field['type'] ?? 'String';
            $description = $field['description'] ?? null;
            
            if ($description) {
                $sdl[] = '  """' . $description . '"""';
            }
            
            $args = '';
            if (!empty($field['args'])) {
                $argDefs = [];
                foreach ($field['args'] as $argName => $argType) {
                    $argDefs[] = "$argName: $argType";
                }
                $args = '(' . implode(', ', $argDefs) . ')';
            }
            
            $sdl[] = "  {$fieldName}{$args}: {$type}";
        }
        
        $sdl[] = '}';
        
        return implode("\n", $sdl);
    }
}