<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class ComponentNode extends AbstractNode
{
    private string $name;
    private $props;

    public function __construct(string $name, $props = null)
    {
        $this->name = $name;
        $this->props = $props;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProps()
    {
        return $this->props;
    }
}