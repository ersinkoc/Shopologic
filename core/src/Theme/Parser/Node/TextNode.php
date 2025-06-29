<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class TextNode extends AbstractNode
{
    private string $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    public function getText(): string
    {
        return $this->text;
    }
}