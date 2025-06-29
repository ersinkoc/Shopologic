<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\GraphQL;

class Scalar
{
    protected string $name;
    protected ?string $description;
    protected $serialize;
    protected $parseValue;
    protected $parseLiteral;

    public function __construct(string $name, array $definition)
    {
        $this->name = $name;
        $this->description = $definition['description'] ?? null;
        $this->serialize = $definition['serialize'] ?? null;
        $this->parseValue = $definition['parseValue'] ?? null;
        $this->parseLiteral = $definition['parseLiteral'] ?? null;
    }

    /**
     * Get scalar name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Serialize value
     */
    public function serialize(mixed $value): mixed
    {
        if ($this->serialize) {
            return call_user_func($this->serialize, $value);
        }
        
        return $value;
    }

    /**
     * Parse value
     */
    public function parseValue(mixed $value): mixed
    {
        if ($this->parseValue) {
            return call_user_func($this->parseValue, $value);
        }
        
        return $value;
    }

    /**
     * Parse literal
     */
    public function parseLiteral(array $ast): mixed
    {
        if ($this->parseLiteral) {
            return call_user_func($this->parseLiteral, $ast);
        }
        
        return $ast['value'] ?? null;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => 'scalar',
            'description' => $this->description,
        ];
    }
}