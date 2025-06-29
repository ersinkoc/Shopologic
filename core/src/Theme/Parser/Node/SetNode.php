<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class SetNode extends AbstractNode
{
    private string $variable;
    private $value;

    public function __construct(string $variable, $value)
    {
        $this->variable = $variable;
        $this->value = $value;
    }

    public function getVariable(): string
    {
        return $this->variable;
    }

    public function getValue()
    {
        return $this->value;
    }
}