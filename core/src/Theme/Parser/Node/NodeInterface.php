<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser\Node;

interface NodeInterface
{
    /**
     * Get node type
     */
    public function getType(): string;
    
    /**
     * Get child nodes
     */
    public function getChildren(): array;
}