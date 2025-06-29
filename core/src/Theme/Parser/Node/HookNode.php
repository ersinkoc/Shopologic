<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class HookNode extends AbstractNode
{
    private string $name;
    private $data;

    public function __construct(string $name, $data = null)
    {
        $this->name = $name;
        $this->data = $data;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData()
    {
        return $this->data;
    }
}