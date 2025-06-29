<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class PrintNode extends AbstractNode
{
    private $expression;
    private array $filters;

    public function __construct($expression, array $filters = [])
    {
        $this->expression = $expression;
        $this->filters = $filters;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}