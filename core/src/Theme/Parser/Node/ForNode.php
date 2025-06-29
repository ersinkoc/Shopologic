<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class ForNode extends AbstractNode
{
    private string $itemVariable;
    private ?string $keyVariable;
    private $collection;
    private array $body;
    private array $elseBody;

    public function __construct(string $itemVariable, ?string $keyVariable, $collection, array $body, array $elseBody = [])
    {
        $this->itemVariable = $itemVariable;
        $this->keyVariable = $keyVariable;
        $this->collection = $collection;
        $this->body = $body;
        $this->elseBody = $elseBody;
    }

    public function getItemVariable(): string
    {
        return $this->itemVariable;
    }

    public function getKeyVariable(): ?string
    {
        return $this->keyVariable;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function getBody(): array
    {
        return $this->body;
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