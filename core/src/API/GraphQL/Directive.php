<?php

declare(strict_types=1);

namespace Shopologic\Core\Api\GraphQL;

class Directive
{
    protected string $name;
    protected ?string $description;
    protected array $locations = [];
    protected array $args = [];

    public function __construct(string $name, array $definition)
    {
        $this->name = $name;
        $this->description = $definition['description'] ?? null;
        $this->locations = $definition['locations'] ?? [];
        $this->args = $definition['args'] ?? [];
    }

    /**
     * Get directive name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'locations' => $this->locations,
            'args' => $this->args,
        ];
    }
}