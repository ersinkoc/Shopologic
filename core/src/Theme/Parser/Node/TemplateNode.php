<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class TemplateNode extends AbstractNode
{
    private ?string $extends = null;
    private array $blocks = [];

    public function __construct(array $children = [])
    {
        $this->children = $children;
    }

    public function setExtends(string $parent): void
    {
        $this->extends = $parent;
    }

    public function getExtends(): ?string
    {
        return $this->extends;
    }

    public function setBlocks(array $blocks): void
    {
        $this->blocks = $blocks;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }
}