<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

abstract class AbstractNode implements NodeInterface
{
    protected array $children = [];

    public function getType(): string
    {
        $class = get_class($this);
        return substr($class, strrpos($class, '\\') + 1);
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(NodeInterface $node): void
    {
        $this->children[] = $node;
    }
}