<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class BlockNode extends AbstractNode
{
    private string $name;
    private array $body;

    public function __construct(string $name, array $body)
    {
        $this->name = $name;
        $this->body = $body;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBody(): array
    {
        return $this->body;
    }
}