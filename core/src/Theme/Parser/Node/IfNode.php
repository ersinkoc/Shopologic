<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class IfNode extends AbstractNode
{
    private $condition;
    private array $ifBody;
    private array $elseIfClauses;
    private array $elseBody;

    public function __construct($condition, array $ifBody, array $elseIfClauses = [], array $elseBody = [])
    {
        $this->condition = $condition;
        $this->ifBody = $ifBody;
        $this->elseIfClauses = $elseIfClauses;
        $this->elseBody = $elseBody;
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function getIfBody(): array
    {
        return $this->ifBody;
    }

    public function getElseIfClauses(): array
    {
        return $this->elseIfClauses;
    }

    public function getElseBody(): array
    {
        return $this->elseBody;
    }

    public function hasElse(): bool
    {
        return !empty($this->elseBody);
    }
}