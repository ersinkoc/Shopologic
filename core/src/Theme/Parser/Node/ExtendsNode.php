<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class ExtendsNode extends AbstractNode
{
    private string $parent;

    public function __construct(string $parent)
    {
        $this->parent = $parent;
    }

    public function getParent(): string
    {
        return $this->parent;
    }
}