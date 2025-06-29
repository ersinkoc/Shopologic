<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

class IncludeNode extends AbstractNode
{
    private string $template;
    private $variables;

    public function __construct(string $template, $variables = null)
    {
        $this->template = $template;
        $this->variables = $variables;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getVariables()
    {
        return $this->variables;
    }
}